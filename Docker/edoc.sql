-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 11 mars 2026 à 19:06
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `edoc`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

CREATE TABLE `admin` (
  `admid` int(11) NOT NULL,
  `admemail` varchar(255) NOT NULL,
  `admname` varchar(255) NOT NULL,
  `admpassword` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admin`
--

INSERT INTO `admin` (`admid`, `admemail`, `admname`, `admpassword`, `created_at`) VALUES
(1, 'admin@psyspace.com', 'Admin Principal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-03-03 12:47:46');

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `patient_name` varchar(150) NOT NULL,
  `app_date` datetime NOT NULL,
  `app_type` varchar(50) DEFAULT 'Consultation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `patient_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `appointments`
--

INSERT INTO `appointments` (`id`, `doctor_id`, `patient_id`, `patient_name`, `app_date`, `app_type`, `created_at`, `patient_phone`) VALUES
(47, 26, 13, 'Amin Gara', '2026-03-08 12:00:00', 'Consultation', '2026-03-08 10:09:18', '54861286'),
(48, 26, 14, 'mongi terma', '2026-03-16 10:00:00', 'Consultation', '2026-03-09 07:42:12', '4563214'),
(49, 26, 15, 'theht', '2026-03-25 08:30:00', 'Consultation', '2026-03-09 10:50:32', '741258'),
(50, 28, 13, 'SAMIR LOUSIF', '2026-03-10 08:00:00', 'Consultation', '2026-03-09 17:11:04', '54861286');

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `date_consultation` datetime DEFAULT current_timestamp(),
  `transcription_brute` text DEFAULT NULL,
  `resume_ia` text DEFAULT NULL,
  `duree_minutes` int(11) DEFAULT NULL,
  `emotion_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `consultations`
--

INSERT INTO `consultations` (`id`, `patient_id`, `appointment_id`, `doctor_id`, `date_consultation`, `transcription_brute`, `resume_ia`, `duree_minutes`, `emotion_data`) VALUES
(23, 13, 47, 26, '2026-03-08 20:40:32', 'bonjour Docteur je m\'appelle comme tu sais amine gara j\'ai 39 ans j\'habite en Tunisie et j\'ai des problèmes que je comprends pas du tout  je me sens pas bien ces jours-là j\'ai des problèmes dans la vie je suis un peu perdu  j\'ai mal à la tête ces jours-là je me sens pas bien je suis triste  je veux me suicider', '{\"synthese_courte\":\"Le patient, Amin Gara, âgé de 39 ans, présente des symptômes de détresse psychologique intense, avec des idées suicidaires. Il exprime un sentiment de perte et de désespoir face à ses problèmes de vie.\",\"observation\":\"Lors de la première séance, le patient a exprimé ouvertement ses difficultés à faire face à ses problèmes de vie, ressentant une grande souffrance psychologique. Il a mentionné des maux de tête et une tristesse profonde, indiquant un état émotionnel fragile. Le patient a également exprimé des idées suicidaires, ce qui nécessite une attention immédiate. Son discours est marqué par une grande confusion et un sentiment d\'impuissance face à ses difficultés.\",\"humeur\":\"Le patient présente une humeur triste et désespérée, avec une tendance à l\'anxiété et à la rumination. Il semble avoir perdu espoir et se sent submergé par ses problèmes. Les idées suicidaires exprimées sont un indicateur d\'une détresse psychologique extrême.\",\"alliance\":\"Une alliance thérapeutique a commencé à se former lors de cette première séance, le patient ayant exprimé son désir de trouver de l\'aide et de comprendre ses problèmes. Il a montré une certaine ouverture à l\'idée de travailler sur ses difficultés avec le thérapeute. Cependant, il est important de renforcer cette alliance pour favoriser la confiance et la collaboration.\",\"vigilance\":\"Le patient est vigilant à ses symptômes et à ses émotions, mais il semble avoir du mal à les gérer de manière efficace. Il est important de l\'aider à développer des stratégies pour faire face à ses difficultés et améliorer sa résilience. La présence d\'idées suicidaires nécessite une surveillance étroite et une intervention ciblée.\",\"axes\":\"Les axes principaux à explorer dans les prochaines séances incluent l\'évaluation de la détresse psychologique, l\'exploration des problèmes de vie spécifiques, et le développement de stratégies pour améliorer la résilience et gérer les émotions négatives. Il est également important de considérer les facteurs de risque et de protection pour le suicide.\",\"hypotheses_diag\":[\"Trouble dépressif majeur (6A70)\"],\"objectifs_next\":[\"Réduire les idées suicidaires\",\"Améliorer la gestion des émotions négatives\",\"Développer des stratégies pour faire face aux problèmes de vie\"],\"plan_therapeutique\":\"La prochaine séance sera axée sur l\'évaluation plus approfondie de la détresse psychologique et des idées suicidaires, ainsi que sur le début de la mise en place de stratégies pour améliorer la gestion des émotions et la résilience. Il sera également important de discuter des ressources et des réseaux de soutien disponibles pour le patient.\",\"recommandations\":\"Il est recommandé de suivre de près l\'évolution des idées suicidaires et de la détresse psychologique. Le patient devrait être encouragé à contacter le thérapeute ou les services d\'urgence en cas de crise. La mise en place d\'un plan de sécurité pour prévenir les actes suicidaires est également cruciale.\",\"niveau_risque\":\"critique\"}', 0, '[0,0,0,100]'),
(24, 14, 48, 26, '2026-03-09 08:42:47', 'glerglzlggzlgzl', '', 0, '[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]'),
(25, 13, 50, 28, '2026-03-09 18:19:36', 'Aujourd\'hui, je me sens assez malade etouffé et j\'ai l\'impression que mon niveau d\'anxiété est monté d\'un cran cette semaine. Le moment qui a tout déclenché, c\'est quand [expliquer l\'événement récent] ; sur le coup, j\'ai réagi en [votre réaction], ce qui m\'a rappelé exactement ce qu\'on avait commencé à explorer la dernière fois concernant [sujet précédent]. Je me rends compte que je tourne en boucle sur la pensée que [votre pensée négative] et ça m\'empêche d\'avancer sereinement. Pour notre séance de maintenant, j\'aimerais vraiment qu\'on se concentre sur ce point précis pour comprendre pourquoi ce schéma se répète et, si possible, que vous m\'aidiez à trouver un moyen concret de ne plus me laisser submerger quand ça arrive', '{\"synthese\":\"Le patient, Samir Lousif, a exprimé un sentiment d\'étouffement et une augmentation de son niveau d\'anxiété lors de cette première séance. Il a partagé un événement récent qui a déclenché ces sentiments et a exprimé le désir de comprendre et de gérer ces schémas répétitifs. L\'objectif principal de cette séance a été d\'identifier les causes sous-jacentes de ces sentiments et de trouver des moyens concrets pour les gérer.\",\"contenu_aborde\":\"Le patient a abordé le thème de l\'anxiété et de la détresse, en particulier en lien avec un événement récent qui a déclenché ces sentiments. Il a également exprimé des pensées négatives qui le hantent et l\'empêchent d\'avancer sereinement. Le patient a montré une volonté de comprendre et de gérer ces schémas répétitifs. Les thèmes de la résilience et de la gestion de l\'anxiété ont été abordés.\",\"etat_clinique\":\"L\'état émotionnel du patient est caractérisé par une anxiété élevée, avec un score de 16 sur l\'échelle d\'anxiété. Le patient a également exprimé des sentiments d\'étouffement et de détresse, mais sans signes de risque suicidaire ou de détresse extrême. Les cognitions du patient sont principalement négatives, avec des pensées qui le hantent et l\'empêchent d\'avancer.\",\"alliance\":\"Le lien thérapeutique a été établi de manière positive, avec le patient montrant une volonté de partager ses sentiments et ses pensées. Le patient a exprimé une confiance dans le processus thérapeutique et a montré une ouverture à l\'exploration de ses émotions et de ses pensées.\",\"points_vigilance\":[\"Suivi de l\'anxiété\",\"Identification des déclencheurs de l\'anxiété\"],\"hypotheses_diag\":[\"Trouble anxieux non spécifié (6A05)\"],\"objectifs_prochaine_seance\":[\"Comprendre les causes sous-jacentes de l\'anxiété\",\"Identifier des stratégies de gestion de l\'anxiété\",\"Développer une planification pour gérer les situations déclenchantes\"],\"niveau_risque\":\"faible\"}', 0, '[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]');

-- --------------------------------------------------------

--
-- Structure de la table `doctor`
--

CREATE TABLE `doctor` (
  `docid` int(11) NOT NULL,
  `docemail` varchar(255) NOT NULL,
  `docname` varchar(255) NOT NULL,
  `docpassword` varchar(255) NOT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `order_num` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `docphone` varchar(20) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctor`
--

INSERT INTO `doctor` (`docid`, `docemail`, `docname`, `docpassword`, `otp_code`, `status`, `reset_token`, `token_expiry`, `photo`, `specialty`, `order_num`, `bio`, `docphone`, `remember_token`) VALUES
(26, 'hamadiaouina192@gmail.com', 'Slimane', '$2y$10$vfY.oqtU15sKNeZUwYZkteiWol8Cg8SPGqTVzgSmo8xdzXlKqfZWW', NULL, 'active', 'adb693f5b4ff64293fde796dc09bbc46603c88b4770e9cc5860bb0bfee38762f', '0000-00-00 00:00:00', 'uploads/avatars/doc_26_1772834249.jpg', 'Suicide', '', '', '54861286', 'ad6cfdcda3c5e2dba17dc6193246d1e245e10fda023df4ad6e0ac67496108675'),
(28, 'hamadi.aouina@etudiant-isi.utm.tn', 'Aouina', '$2y$10$/ZE4W9xSQbIjDWZdVKp30elvVyZHGZ57OS7WL/hHTSW7/7vAio9jy', NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `pname` varchar(150) NOT NULL,
  `pphone` varchar(20) DEFAULT NULL,
  `pdob` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `pname`, `pphone`, `pdob`, `created_at`) VALUES
(13, 'Amin Gara', '54861286', NULL, '2026-03-08 10:09:18'),
(14, 'mongi terma', '4563214', NULL, '2026-03-09 07:42:12'),
(15, 'theht', '741258', NULL, '2026-03-09 10:50:32');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admid`),
  ADD UNIQUE KEY `admemail` (`admemail`);

--
-- Index pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`docid`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin`
--
ALTER TABLE `admin`
  MODIFY `admid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT pour la table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `docid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
