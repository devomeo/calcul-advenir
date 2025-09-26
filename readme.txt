=== Advenir Eligibility Wizard ===
Contributors: calcul-advenir
Tags: advenir, electric vehicles, subsidy, wizard, questionnaire
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A progressive questionnaire to help French site owners estimer l'éligibilité à l'aide Advenir et calculer les montants indicatifs.

== Description ==
Advenir Eligibility Wizard propose un parcours guidé en plusieurs étapes :

* Les questions sont chargées depuis une configuration JSON administrable.
* Les réponses sont évaluées via une API REST sécurisée pour déterminer l'éligibilité probable et estimer les montants par point de charge et le total.
* Les scénarios, les textes de résultat et les barèmes peuvent être adaptés par l'administrateur du site sans modifier le code.

Le plugin n'enregistre aucune donnée personnelle. Les réponses sont traitées uniquement côté navigateur et envoyées temporairement à l'API pour l'évaluation.

== Installation ==
1. Téléversez le dossier `advenir-eligibility-wizard` dans `/wp-content/plugins/` ou installez le ZIP depuis l'interface d'administration.
2. Activez l'extension via le menu Extensions de WordPress.
3. Rendez-vous dans **Advenir Wizard** dans le menu d'administration pour vérifier ou ajuster la configuration JSON.
4. Ajoutez le shortcode `[advenir_wizard]` dans un article ou une page pour afficher l'assistant.

== JSON de configuration ==
Dans la page d'administration, une zone de texte permet de modifier la configuration :

* `questions` décrit chaque étape (type `single` ou `number`).
* `scenarios` définit les conditions d'éligibilité et les montants (plafond de points, montant par point, messages personnalisés).
* `results` fournit les textes par défaut pour les cas éligibles ou non.

Le plugin embarque un exemple qu'il convient d'adapter aux barèmes officiels. En cas de JSON invalide, l'ancienne configuration est conservée et un message d'erreur est affiché.

== Shortcode ==
`[advenir_wizard]` rend le formulaire complet et charge automatiquement les scripts nécessaires.

== Sécurité et confidentialité ==
* Les requêtes REST nécessitent un nonce WordPress (`wp_rest`).
* Les capacités d'administration sont limitées aux utilisateurs pouvant gérer les options.
* Aucune donnée personnelle n'est stockée ; les réponses ne servent qu'à la requête en cours.

== Générer un fichier POT ==
Pour extraire les chaînes de traduction, vous pouvez utiliser `wp i18n make-pot` ou tout outil équivalent pointant vers le dossier du plugin.

== Changelog ==
= 1.0.0 =
* Première version publique.
