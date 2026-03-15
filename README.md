PsySpace : Architecture Logicielle et Système de Déploiement
1. Objet du Projet
PsySpace est une plateforme de gestion pour les services de soutien psychologique. Ce projet de fin d'études démontre l'implémentation de bonnes pratiques DevOps, incluant la conteneurisation des services et l'automatisation du cycle de vie logiciel via une chaîne CI/CD.

2. Spécifications Techniques
L'application repose sur une pile technologique choisie pour sa robustesse et sa facilité de déploiement en environnement de production.

Serveur Applicatif : PHP 8.2 (Interpréteur FastCGI)

Serveur Web : Apache / Nginx (via Docker)

Gestion d'Infrastructure : Docker Compose (Infrastructure as Code)

Orchestration CI/CD : GitLab CI

3. Déploiement et Exécution
Le projet utilise la virtualisation par conteneurs pour garantir l'homogénéité entre les environnements de développement et de production.

Prérequis Système
Docker Engine v20.10.0+

Docker Compose v2.0.0+

Protocole d'Installation
Récupération des sources :

Bash
git clone https://gitlab.com/votre-nom/psyspace.git
Lancement de l'infrastructure :

Bash
docker-compose up -d --build
Point d'accès :
L'application est exposée sur le port local : http://localhost:8080

4. Pipeline d'Intégration Continue
Le workflow de développement est sécurisé par un pipeline GitLab CI défini dans .gitlab-ci.yml. Ce pipeline assure l'intégrité du système à chaque commit.

Phases de validation :
Static Analysis (Linting) : Exécution d'une analyse syntaxique sur l'ensemble du code source PHP pour identifier les régressions potentielles.

Infrastructure Validation : Vérification de la conformité des descripteurs Docker et simulation de la construction des images.

5. Organisation du Dépôt
Plaintext
├── .gitlab-ci.yml         # Définition des stages et jobs CI/CD
├── docker-compose.yml     # Orchestration des services applicatifs
├── Dockerfile             # Spécifications de construction de l'image
├── index.php              # Point d'entrée de l'application
├── assets/                # Ressources statiques (CSS, JS, images)
└── core/                  # Logique métier et contrôleurs