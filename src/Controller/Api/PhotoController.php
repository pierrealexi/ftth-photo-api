<?php

namespace App\Controller\Api;

use App\Repository\PhotoRepository;
use App\Entity\Photo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class PhotoController extends AbstractController
{
    private PhotoRepository $photoRepository;

    public function __construct(PhotoRepository $photoRepository)
    {
        $this->photoRepository = $photoRepository;
    }

    private function errorResponse(string $message, int $code = 400, string $type = 'error', array $extra = []): JsonResponse
    {
        return $this->json(array_merge([
            'success' => false,
            'error' => [
                'code' => $code,
                'type' => $type,
                'message' => $message
            ]
        ], $extra), $code);
    }

    #[Route('/api/photos', name: 'api_photos_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max((int) $request->query->get('page', 1), 1);
        $limit = max((int) $request->query->get('limit', 10), 1);
        $offset = ($page - 1) * $limit;

        $qb = $this->photoRepository->createQueryBuilder('p');

        if ($request->query->get('prestationReference')) {
            $qb->andWhere('p.prestationReference = :prestation')
               ->setParameter('prestation', $request->query->get('prestationReference'));
        }

        if ($request->query->get('internalOrder')) {
            $qb->andWhere('p.internalOrder = :order')
               ->setParameter('order', $request->query->get('internalOrder'));
        }

        if ($request->query->get('interventionId')) {
            $qb->andWhere('p.interventionId = :intervention')
               ->setParameter('intervention', $request->query->get('interventionId'));
        }

        if ($request->query->get('filename')) {
            $qb->andWhere('p.filename LIKE :filename')
               ->setParameter('filename', '%' . $request->query->get('filename') . '%');
        }

        $sort = $request->query->get('sort', 'p.createdAt');
        $direction = strtoupper($request->query->get('direction', 'DESC'));
        if (!in_array($direction, ['ASC', 'DESC'])) $direction = 'DESC';

        $qb->orderBy($sort, $direction)
           ->setFirstResult($offset)
           ->setMaxResults($limit);

        $photos = $qb->getQuery()->getResult();

        $total = count($photos);

        $response = [
            'success' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];

        if ($total === 0) {
            $response['message'] = 'No photos found for the given filters';
        } else {
            $response['data'] = $photos;
        }

        return $this->json($response, 200);
    }

    #[Route('/api/photos/{id}', name: 'api_photos_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->errorResponse('Photo not found', 404, 'not_found');
        }

        return $this->json([
            'success' => true,
            'photo' => [
                'id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'originalName' => $photo->getOriginalName(),
                'mimeType' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'path' => $photo->getPath(),
                'prestationReference' => $photo->getPrestationReference(),
                'internalOrder' => $photo->getInternalOrder(),
                'interventionId' => $photo->getInterventionId(),
                'metadata' => $photo->getMetadata(),
                'createdAt' => $photo->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/api/photos', name: 'api_photos_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('photo');

        if (!$file) {
            return $this->errorResponse('No file uploaded', 400, 'validation_error', ['field' => 'photo']);
        }

        $mimeType = $file->getClientMimeType();
        $size = $file->getSize();
        $allowedMimeTypes = ['image/jpeg', 'image/png'];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return $this->errorResponse('Invalid file type', 400, 'validation_error', ['mime_type' => $mimeType]);
        }

        if ($size > 5 * 1024 * 1024) {
            return $this->errorResponse('File too large (max 5MB)', 400, 'validation_error');
        }

        $newFilename = uniqid() . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->errorResponse('Failed to save file', 500, 'server_error', ['exception' => $e->getMessage()]);
        }

        $metadata = null;
        if ($mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($uploadDir . '/' . $newFilename);
            if ($exif) {
                $metadata = [
                    'camera' => $exif['Model'] ?? null,
                    'takenAt' => $exif['DateTimeOriginal'] ?? null,
                    'latitude' => $exif['GPSLatitude'] ?? null,
                    'longitude' => $exif['GPSLongitude'] ?? null,
                ];
            }
        }

        $photo = new Photo();
        $photo->setFilename($newFilename);
        $photo->setOriginalName($file->getClientOriginalName());
        $photo->setMimeType($mimeType);
        $photo->setSize($size);
        $photo->setPath('/uploads/' . $newFilename);
        $photo->setPrestationReference($request->request->get('prestationReference'));
        $photo->setInternalOrder($request->request->get('internalOrder'));
        $photo->setInterventionId($request->request->getInt('interventionId'));
        $photo->setMetadata($metadata);
        $photo->setCreatedAt(new \DateTimeImmutable());

        $this->photoRepository->save($photo, true);

        return $this->json([
            'success' => true,
            'photo' => [
                'id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'originalName' => $photo->getOriginalName(),
                'mimeType' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'path' => $photo->getPath(),
                'prestationReference' => $photo->getPrestationReference(),
                'internalOrder' => $photo->getInternalOrder(),
                'interventionId' => $photo->getInterventionId(),
                'metadata' => $photo->getMetadata(),
                'createdAt' => $photo->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    #[Route('/api/photos/{id}', name: 'api_photos_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $photo = $this->photoRepository->find($id);
        if (!$photo) {
            return $this->errorResponse('Photo not found', 404, 'not_found');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

        /** @var UploadedFile|null $file */
        $file = $request->files->get('photo');
        if ($file instanceof UploadedFile) {
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize() ?? 0;

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return $this->errorResponse('Invalid file type', 400, 'validation_error', ['mime_type' => $mimeType]);
            }

            if ($size > 5 * 1024 * 1024) {
                return $this->errorResponse('File too large (max 5MB)', 400, 'validation_error');
            }

            $oldPath = $photo->getPath();
            $newFilename = uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadDir, $newFilename);
            } catch (FileException $e) {
                return $this->errorResponse('Failed to save file', 500, 'server_error', ['exception' => $e->getMessage()]);
            }

            $photo->setFilename($newFilename);
            $photo->setOriginalName($file->getClientOriginalName());
            $photo->setMimeType((string) $mimeType);
            $photo->setSize((int) $size);
            $photo->setPath('/uploads/' . $newFilename);

            if ($oldPath) {
                $oldAbsolutePath = $this->getParameter('kernel.project_dir') . '/public' . $oldPath;
                if (is_file($oldAbsolutePath)) {
                    @unlink($oldAbsolutePath);
                }
            }
        }

        if ($request->request->has('prestationReference')) {
            $photo->setPrestationReference($request->request->get('prestationReference') ?: null);
        }

        if ($request->request->has('internalOrder')) {
            $photo->setInternalOrder($request->request->get('internalOrder') ?: null);
        }

        if ($request->request->has('interventionId')) {
            $interventionId = $request->request->get('interventionId');
            $photo->setInterventionId($interventionId === '' || $interventionId === null ? null : (int) $interventionId);
        }

        if ($request->request->has('location')) {
            $photo->setLocation($request->request->get('location') ?: null);
        }

        if ($request->request->has('cameraModel')) {
            $photo->setCameraModel($request->request->get('cameraModel') ?: null);
        }

        if ($request->request->has('dateTaken')) {
            $dateTaken = $request->request->get('dateTaken');
            if ($dateTaken === '' || $dateTaken === null) {
                $photo->setDateTaken(null);
            } else {
                try {
                    $photo->setDateTaken(new \DateTime($dateTaken));
                } catch (\Exception) {
                    return $this->errorResponse('Invalid date format for dateTaken', 400, 'validation_error');
                }
            }
        }

        $this->photoRepository->save($photo, true);

        return $this->json([
            'success' => true,
            'photo' => [
                'id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'originalName' => $photo->getOriginalName(),
                'mimeType' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'path' => $photo->getPath(),
                'prestationReference' => $photo->getPrestationReference(),
                'internalOrder' => $photo->getInternalOrder(),
                'interventionId' => $photo->getInterventionId(),
                'location' => $photo->getLocation(),
                'cameraModel' => $photo->getCameraModel(),
                'dateTaken' => $photo->getDateTaken()?->format('Y-m-d H:i:s'),
                'metadata' => $photo->getMetadata(),
                'createdAt' => $photo->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/api/photos/{id}', name: 'api_photos_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $photo = $this->photoRepository->find($id);
        if (!$photo) {
            return $this->errorResponse('Photo not found', 404, 'not_found');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $photo->getPath();

        if (file_exists($filePath) && !unlink($filePath)) {
            return $this->errorResponse('Unable to delete file', 500, 'server_error');
        }

        $em = $this->photoRepository->getEm();
        $em->remove($photo);
        $em->flush();

        return $this->json(['success' => true], 204);
    }

    #[Route('/api/photos/{id}/file', name: 'api_photos_file', methods: ['GET'])]
    public function getFile(int $id): Response
    {
        $photo = $this->photoRepository->find($id);
        if (!$photo) {
            return $this->errorResponse('Photo not found', 404, 'not_found');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $photo->getPath();
        if (!file_exists($filePath)) {
            return $this->errorResponse('File not found on server', 404, 'not_found');
        }

        return new BinaryFileResponse($filePath);
    }
}