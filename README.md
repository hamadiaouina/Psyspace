![CI](https://github.com/hamadiaouina/Psyspace/actions/workflows/php-lint.yml/badge.svg)

# PsySpace : Architecture Logicielle et Système de Déploiement

## 1. Objet du Projet
PsySpace est une plateforme de gestion pour les services de soutien psychologique. Ce projet de fin d'études (PFE) met en avant de bonnes pratiques DevOps, incluant la conteneurisation des services et l’automatisation du cycle de vie logiciel via une chaîne CI/CD.

## 2. Spécifications Techniques
L’application repose sur une pile technologique choisie pour sa robustesse et sa facilité de déploiement.

- **Serveur applicatif** : PHP 8.2 (FastCGI)
- **Serveur web** : Apache / Nginx (via Docker)
- **Infrastructure as Code** : Docker Compose
- **CI/CD** : GitHub Actions (lint PHP + contrôle des secrets)

> Note : la version initiale du document mentionnait GitLab CI. Le dépôt actuel est hébergé sur GitHub et utilise GitHub Actions.

## 3. Déploiement et Exécution

### Prérequis système
- Docker Engine **v20.10+**
- Docker Compose **v2+**

### Installation
1) **Récupérer le code**
```bash
git clone https://github.com/hamadiaouina/Psyspace.git
cd Psyspace
```

2) **Configurer les variables d’environnement**
- Copier le fichier d’exemple et renseigner vos clés :
```bash
cp .env.example .env
```

> Important : **ne jamais commit** le fichier `.env` (il est ignoré par `.gitignore`).

3) **Lancer l’infrastructure**
```bash
docker compose up -d --build
```

### Point d’accès
- Application : `http://localhost:8080`

## 4. Pipeline d’Intégration Continue
Le dépôt est sécurisé par un pipeline CI via **GitHub Actions** (dossier `.github/workflows/`).

### Phases de validation
- **Static Analysis (Linting)** : `php -l` sur l’ensemble des fichiers PHP
- **Secrets Check** : échec du pipeline si un fichier `.env` / `*.env` est suivi par Git

## 5. Organisation du Dépôt
```text
├── .github/workflows/     # CI GitHub Actions
├── docker-compose.yml     # Orchestration des services
├── Dockerfile             # Construction de l'image
├── index.php              # Point d'entrée
├── assets/                # Ressources statiques (CSS, JS, images)
└── core/                  # Logique métier et contrôleurs
```

## 6. Sécurité
- Ne jamais exposer ou commiter des secrets (clés API, tokens).
- Utiliser `.env` uniquement en local/serveur (voir `.env.example`).

## 7. Licence
À définir.