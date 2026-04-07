# FTTH Photo API

API Symfony pour uploader, lister, consulter, supprimer et servir des photos, avec authentification JWT pour l'API et back-office EasyAdmin pour la gestion manuelle.

## Reponse a l'enonce

Ce document est structure pour repondre directement au sujet:
- contexte et objectifs
- taches demandees
- choix techniques et decisions
- instructions pour lancer/tester
- bonus realises

### Contexte (resume)

L'API permet la gestion des photos liees aux interventions FTTH avec:
- persistance base de donnees
- reponses JSON
- contraintes de format/taille
- metadonnees associees

### Objectifs cibles

- recuperer des photos par criteres metier (prestationReference, internalOrder, interventionId)
- gerer les erreurs courantes avec des reponses explicites
- securiser l'acces a l'API

### Taches de l'enonce et couverture

1. Concevoir les entites necessaires
Resultat:
- Entite Photo implementee avec attributs fichier, metier, metadata et dates
- Entite User implementee pour l'authentification

2. Developper les endpoints API pour les criteres de recherche
Resultat:
- Endpoints REST en place sur /api/photos (list, detail, upload, delete, file)
- Endpoint de mise a jour disponible (PUT/PATCH /api/photos/{id})
- Filtres par prestationReference, internalOrder, interventionId

3. Gerer les erreurs courantes
Resultat:
- Gestion des cas photo introuvable, absence de fichier, format invalide, taille depassee, fichier absent sur disque
- Reponses JSON homogenes (success/error + code HTTP adapte)

4. Proposer et implementer la securisation
Resultat:
- Authentification JWT stateless pour /api
- Controle d'acces via firewalls et access_control

5. Fournir code + lancement/test + explications techniques
Resultat:
- Code source organise par couches (Entity, Repository, Controller, Fixtures, Tests)
- Instructions d'installation, migration, fixtures et execution dans ce README
- Explication des choix techniques detaillee ci-dessous

### Bonus realises

- Interface d'administration EasyAdmin (dashboard + CRUD)
- Tests fonctionnels API avec PHPUnit
- Correctif de redirection login admin (conflit de firewall resolu)
- Edition d'une photo existante depuis l'admin (fichier optionnel en modification)
- Suppression physique du fichier dans public/uploads lors d'un delete depuis l'admin
- Filtres admin alignes avec les criteres API (prestationReference, internalOrder, interventionId, filename)

## 1. Objectif du projet

Ce projet répond a un besoin simple: centraliser des photos terrain (FTTH), les relier a des references metier, et les exposer via une API securisee.

Objectifs fonctionnels:
- authentifier les utilisateurs API avec JWT
- uploader des images (JPEG/PNG)
- stocker des metadonnees techniques et metier
- recuperer les photos via API JSON
- telecharger/afficher le fichier image brut
- administrer les photos via une interface web

## 2. Stack technique

- PHP >= 8.2
- Symfony 7.4
- Doctrine ORM + Migrations
- LexikJWTAuthenticationBundle (JWT)
- EasyAdmin 5
- PHPUnit 11
- Base de donnees: MySQL (configuree dans .env)
- Docker Compose present (service PostgreSQL disponible dans compose.yaml)

## 3. Ce qui a ete fait (quoi, pourquoi, comment)

### 3.1 Modele de donnees Photo

Quoi:
- Creation de l'entite Photo avec:
  - fichier: filename, originalName, mimeType, size, path
  - metier: prestationReference, internalOrder, interventionId
  - metadonnees: metadata (JSON)
  - traque temporelle: createdAt
  - extension recente: dateTaken, location, cameraModel

Pourquoi:
- separer les informations de stockage fichier des informations metier
- permettre des recherches par references metier
- conserver des infos EXIF ou contextuelles

Comment:
- entite Doctrine dans src/Entity/Photo.php
- migration initiale de la table photo
- migration d'evolution pour les champs dateTaken/location/cameraModel

### 3.2 Modele utilisateur et authentification

Quoi:
- Creation d'une entite User simple (email, roles, password)
- Provider Symfony base sur l'email
- Password hasher actif

Pourquoi:
- disposer d'une authentification standard Symfony, compatible JWT

Comment:
- entite dans src/Entity/User.php
- migration de creation de la table user
- fixture UserFixtures pour creer un utilisateur de test

### 3.3 API JWT

Quoi:
- Endpoint POST /api/login gere par le firewall (json_login)
- Generation de token JWT via Lexik

Pourquoi:
- securiser l'acces API en mode stateless
- permettre l'integration avec clients mobiles/front/back

Comment:
- firewall login configure sur ^/api/login
- firewall api configure sur ^/api avec jwt: ~
- acces_control:
  - /api/login en PUBLIC_ACCESS
  - /api/* en IS_AUTHENTICATED_FULLY

### 3.4 Endpoints Photo

Quoi:
- GET /api/photos: liste paginee + filtres
- GET /api/photos/{id}: detail d'une photo
- POST /api/photos: upload d'image
- PUT/PATCH /api/photos/{id}: mise a jour des metadonnees et remplacement de fichier optionnel
- DELETE /api/photos/{id}: suppression logique (DB + fichier disque)
- GET /api/photos/{id}/file: recuperation du fichier binaire

Pourquoi:
- couvrir le cycle de vie complet d'une photo via API

Comment:
- controller dedie dans src/Controller/Api/PhotoController.php
- validations principales sur upload:
  - presence du fichier
  - type MIME autorise (image/jpeg, image/png)
  - taille max 5 Mo
- sur update:
  - mise a jour partielle des champs metier (prestationReference, internalOrder, interventionId, location, cameraModel, dateTaken)
  - remplacement optionnel du fichier avec les memes regles de validation
  - suppression de l'ancien fichier apres remplacement
- extraction EXIF conditionnelle (si JPEG + extension EXIF disponible)
- stockage des fichiers dans public/uploads
- reponses JSON homogenes avec structure success/error

### 3.5 Back-office EasyAdmin

Quoi:
- Dashboard admin sur /admin
- CRUD photo via PhotoCrudController
- Upload de fichier depuis le formulaire admin
- Modification d'une photo existante (metadonnees + remplacement de fichier optionnel)
- Suppression coherente (base + fichier physique)
- Filtres sur la liste admin pour retrouver rapidement les photos

Pourquoi:
- offrir une interface de moderation/consultation rapide sans passer par un client API

Comment:
- DashboardController avec route admin
- PhotoCrudController:
  - champ fichier (imageFile) dans le formulaire
  - deplacement du fichier vers public/uploads
  - en edition: remplacement du fichier uniquement si un nouveau fichier est fourni
  - en edition: suppression de l'ancien fichier lors d'un remplacement
  - en suppression: suppression du fichier dans public/uploads avant suppression de l'entite
  - filtres EasyAdmin disponibles: prestationReference, internalOrder, interventionId, filename, dateTaken, createdAt
  - mapping des attributs techniques vers l'entite

### 3.6 Ecran de login admin + redirection

Quoi:
- formulaire de login admin sur /admin/login
- firewall form_login sur /admin
- logout sur /admin/logout

Pourquoi:
- proteger le back-office et limiter son acces aux admins

Comment:
- SecurityController pour afficher le formulaire
- template Twig admin/login.html.twig
- regles access_control:
  - /admin/login en PUBLIC_ACCESS
  - /admin en ROLE_ADMIN

Correction recente appliquee:
- Un firewall dedie a /admin/login avec security: false interceptait la requete POST de login et empechait le traitement form_login.
- Le firewall admin_login a ete supprime pour laisser le firewall admin gerer /admin/login (GET + POST).
- Resultat attendu: authentification fonctionnelle puis redirection vers /admin (ou referer si disponible).

### 3.7 Tests automatises

Quoi:
- suite de tests fonctionnels API (PhotoApiTest)

Pourquoi:
- verifier les parcours critiques et regressions

Comment:
- recuperation d'un JWT en setUp
- tests des cas suivants:
  - upload valide
  - upload sans fichier
  - listing
  - acces sans token
  - suppression photo existante/inexistante
  - recuperation fichier photo existant/inexistant

## 4. Arborescence utile

- src/Controller/Api: endpoints API login + photo
- src/Controller/Admin: dashboard, login admin, CRUD photo
- src/Entity: modeles Photo et User
- src/Repository: repository Photo
- src/DataFixtures: jeux de donnees de test
- config/packages/security.yaml: firewalls + controles d'acces
- config/packages/lexik_jwt_authentication.yaml: cles JWT
- migrations: schema evolutif de la base
- tests/Controller/PhotoApiTest.php: tests fonctionnels API

## 5. Installation et demarrage

### 5.1 Prerequis

- PHP 8.2+
- Composer
- MySQL 8+
- OpenSSL (pour les cles JWT)

### 5.2 Installation

```bash
composer install
```

### 5.3 Configuration environnement

Verifier dans .env:
- DATABASE_URL pointe vers votre base MySQL
- JWT_SECRET_KEY, JWT_PUBLIC_KEY, JWT_PASSPHRASE sont renseignes

Generer les cles JWT si necessaire:

```bash
php bin/console lexik:jwt:generate-keypair
```

### 5.4 Base de donnees

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

### 5.5 Lancer l'application

```bash
symfony server:start
```

Ou avec PHP natif:

```bash
php -S 127.0.0.1:8000 -t public
```

## 6. Utilisation rapide de l'API

### 6.1 Login API

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

### 6.2 Upload photo

```bash
curl -X POST http://127.0.0.1:8000/api/photos \
  -H "Authorization: Bearer <TOKEN>" \
  -F "photo=@/chemin/image.jpg" \
  -F "prestationReference=PREST-001" \
  -F "internalOrder=IO-001" \
  -F "interventionId=123"
```

### 6.3 Lister les photos

```bash
curl -X GET "http://127.0.0.1:8000/api/photos?page=1&limit=10" \
  -H "Authorization: Bearer <TOKEN>"
```

### 6.4 Recuperer le fichier image

```bash
curl -X GET http://127.0.0.1:8000/api/photos/1/file \
  -H "Authorization: Bearer <TOKEN>" \
  --output photo.jpg
```

### 6.5 Mettre a jour une photo existante

Mise a jour de metadonnees (sans remplacer le fichier):

```bash
curl -X PATCH http://127.0.0.1:8000/api/photos/1 \
  -H "Authorization: Bearer <TOKEN>" \
  -F "prestationReference=PREST-002" \
  -F "internalOrder=IO-002" \
  -F "interventionId=456" \
  -F "location=Nantes" \
  -F "cameraModel=Canon EOS" \
  -F "dateTaken=2026-04-01 10:30:00"
```

Mise a jour avec remplacement du fichier:

```bash
curl -X PATCH http://127.0.0.1:8000/api/photos/1 \
  -H "Authorization: Bearer <TOKEN>" \
  -F "photo=@/chemin/nouvelle-image.jpg"
```

## 7. Back-office admin

- URL login: /admin/login
- URL dashboard: /admin
- URL logout: /admin/logout

Important:
- L'acces /admin requiert ROLE_ADMIN.
- La fixture fournie cree un utilisateur ROLE_USER. Pour l'acces admin, il faut attribuer ROLE_ADMIN a un utilisateur en base.
- Des filtres sont disponibles dans la liste admin des photos: prestationReference, internalOrder, interventionId, filename, dateTaken et createdAt.

## 8. Execution des tests

```bash
php bin/phpunit
```


## 10. Ameliorations que j'aurais pu faire

Ameliorations prioritaires:
- pagination complete sur la liste API avec un total global fiable (requete COUNT dediee)
- whitelisting strict des champs de tri pour eviter tout tri invalide
- harmonisation definitive de la base (MySQL ou PostgreSQL) sur tous les environnements
- role admin provisionne automatiquement via fixtures ou commande de bootstrap

Ameliorations techniques:
- ajout d'un endpoint de recherche avancee (combinaisons de filtres + bornes de dates)
- ajout d'une strategie de nommage des fichiers plus robuste (UUID + extension detectee)
- ajout d'une couche de services pour isoler la logique de gestion des fichiers du controller
- ajout d'une suppression differée des fichiers (queue) pour les gros volumes

Ameliorations qualite et exploitation:
- couverture de tests plus large (tests update avec remplacement de fichier, cas limites de validation)
- documentation OpenAPI/Swagger pour faciliter l'integration
- journalisation centralisee des erreurs (upload, suppression, droits disque)
- limitation de debit (rate limiting) sur les endpoints sensibles (login/upload)

## 11. Resume

Le projet implemente une API photo securisee par JWT avec un back-office EasyAdmin. Le coeur fonctionnel est en place (auth, upload, listing, suppression, consultation fichier, tests). Cote admin, l'edition d'une photo existante est disponible et la suppression supprime aussi le fichier physique dans uploads. La correction de la redirection admin a ete effectuee en consolidant le traitement de /admin/login dans un seul firewall form_login.

