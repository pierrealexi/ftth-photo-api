<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Photo;

class PhotoApiTest extends WebTestCase
{
    private $client;
    private string $jwt;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password'
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->jwt = $data['token'] ?? null;

        if (!$this->jwt) {
            $this->markTestSkipped('Impossible de récupérer le JWT pour les tests.');
        }
    }

    public function testUploadPhoto(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        $sourceFile = $fixturesDir . '/sample.jpg';
        $testFile = $fixturesDir . '/test-photo.png';

        copy($sourceFile, $testFile);

        $uploadedFile = new UploadedFile(
            $testFile,
            'test-photo.png',
            'image/png',
            null,
            true 
        );

        $this->client->request(
            'POST',
            '/api/photos',
            [],
            ['photo' => $uploadedFile],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt,
                'CONTENT_TYPE' => 'multipart/form-data'
            ]
        );

        $response = $this->client->getResponse();

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('photo', $data);
    }

    public function testListPhotos(): void
    {
        $this->client->request(
            'GET',
            '/api/photos',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testPhotoFileNotFound(): void
    {
        $this->client->request(
            'GET',
            '/api/photos/9999', 
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testUploadWithoutFile(): void
    {
        $this->client->request(
            'POST',
            '/api/photos',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt, 'CONTENT_TYPE' => 'multipart/form-data']
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testDeletePhoto(): void
    {
        $photo = new Photo();
        $photo->setFilename('test-delete.jpg');
        $photo->setOriginalName('test-delete.jpg');
        $photo->setMimeType('image/jpeg');
        $photo->setSize(1024);
        $photo->setPath('/uploads/test-delete.jpg');
        $photo->setCreatedAt(new \DateTimeImmutable());

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($photo);
        $em->flush();

        $photoId = $photo->getId();

        // 2️⃣ Requête DELETE
        $this->client->request(
            'DELETE',
            '/api/photos/' . $photoId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        // 3️⃣ Assertion
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdatePhotoMetadata(): void
    {
        $photo = new Photo();
        $photo->setFilename('test-update.jpg');
        $photo->setOriginalName('test-update.jpg');
        $photo->setMimeType('image/jpeg');
        $photo->setSize(1024);
        $photo->setPath('/uploads/test-update.jpg');
        $photo->setCreatedAt(new \DateTimeImmutable());

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($photo);
        $em->flush();

        $this->client->request(
            'PATCH',
            '/api/photos/' . $photo->getId(),
            [
                'prestationReference' => 'PREST-UPDATED',
                'internalOrder' => 'ORDER-UPDATED',
                'interventionId' => '456',
                'location' => 'Nantes',
                'cameraModel' => 'Canon EOS',
                'dateTaken' => '2026-04-01 10:30:00',
            ],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('PREST-UPDATED', $data['photo']['prestationReference']);
        $this->assertSame('ORDER-UPDATED', $data['photo']['internalOrder']);
        $this->assertSame(456, $data['photo']['interventionId']);
        $this->assertSame('Nantes', $data['photo']['location']);
        $this->assertSame('Canon EOS', $data['photo']['cameraModel']);
        $this->assertNotNull($data['photo']['dateTaken']);
    }

    public function testUpdatePhotoWithInvalidDate(): void
    {
        $photo = new Photo();
        $photo->setFilename('test-update-invalid-date.jpg');
        $photo->setOriginalName('test-update-invalid-date.jpg');
        $photo->setMimeType('image/jpeg');
        $photo->setSize(1024);
        $photo->setPath('/uploads/test-update-invalid-date.jpg');
        $photo->setCreatedAt(new \DateTimeImmutable());

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($photo);
        $em->flush();

        $this->client->request(
            'PATCH',
            '/api/photos/' . $photo->getId(),
            ['dateTaken' => 'invalid-date'],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteNonExistentPhoto(): void
    {
        $this->client->request(
            'DELETE',
            '/api/photos/9999', 
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testGetPhotoFileNotFound(): void
    {
        $this->client->request(
            'GET',
            '/api/photos/9999/file', 
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testGetPhotoFile(): void
    {
        $uploadDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer une image JPEG 1x1 px
        $imagePath = $uploadDir . '/test-file.jpg';
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        $photo = new Photo();
        $photo->setFilename('test-file.jpg');
        $photo->setOriginalName('test-file.jpg');
        $photo->setMimeType('image/jpeg');
        $photo->setSize(filesize($imagePath));
        $photo->setPath('/uploads/test-file.jpg');
        $photo->setCreatedAt(new \DateTimeImmutable());

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($photo);
        $em->flush();

        $this->client->request(
            'GET',
            '/api/photos/' . $photo->getId() . '/file',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwt]
        );

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));

        // Supprime le fichier après le test
        unlink($imagePath);
    }

    public function testAccessWithoutToken(): void
    {
        $this->client->request('GET', '/api/photos');

        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
    }
}