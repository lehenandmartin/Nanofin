<?php

declare(strict_types=1);

return [

    // ── Navigation ────────────────────────────────────────────────
    'nav' => [
        'login'   => 'Connexion',
        'logout'  => 'Déconnexion',
        'admin'   => 'Admin',
        'profile' => 'Profil',
    ],

    // ── Footer ────────────────────────────────────────────────────
    'footer' => [
        'powered_by' => 'Propulsé par',
    ],

    // ── Auth ──────────────────────────────────────────────────────
    'auth' => [
        'login' => [
            'title'    => 'Connexion',
            'username' => 'Nom d\'utilisateur',
            'password' => 'Mot de passe',
            'submit'   => 'Se connecter',
            'invalid'           => 'Identifiants incorrects.',
            'required'          => 'Le nom d\'utilisateur et le mot de passe sont requis.',
            'too_many_attempts' => 'Trop de tentatives. Réessayez dans 15 minutes.',
        ],
        'logout' => [
            'success' => 'Vous avez été déconnecté.',
        ],
        'magic_link' => [
            'identifier'        => 'E-mail ou nom d\'utilisateur',
            'continue'          => 'Continuer',
            'sent'              => 'Un lien de connexion a été envoyé à votre adresse e-mail.',
            'sent_detail'       => 'Cliquez sur le lien dans l\'e-mail pour vous connecter instantanément.',
            'use_password'      => 'Utiliser mon mot de passe',
            'change_identifier' => 'Modifier',
            'expired'           => 'Ce lien de connexion a expiré ou a déjà été utilisé.',
        ],
        'first_login' => [
            'title'   => 'Choisissez un nouveau mot de passe',
            'intro'   => 'Votre mot de passe est temporaire. Veuillez en choisir un nouveau pour continuer.',
            'new'     => 'Nouveau mot de passe',
            'confirm' => 'Confirmer le mot de passe',
            'submit'  => 'Définir le mot de passe',
        ],
        'forgot_password' => [
            'link'   => 'Mot de passe oublié ?',
            'title'  => 'Réinitialisation du mot de passe',
            'intro'  => 'Saisissez l\'adresse e-mail associée à votre compte. Si un compte correspondant existe, vous recevrez un mot de passe temporaire.',
            'email'  => 'Adresse e-mail',
            'submit' => 'Envoyer un mot de passe temporaire',
            'sent'   => 'Si un compte existe avec cette adresse e-mail, vous recevrez un mot de passe temporaire dans quelques instants.',
            'back'   => 'Retour à la connexion',
        ],
    ],

    // ── Setup wizard ─────────────────────────────────────────────
    'setup' => [
        'title'            => 'Configuration initiale',
        'step1'            => 'Créer le compte administrateur',
        'step2'            => 'Configuration Jellyfin',
        'username'         => 'Nom d\'utilisateur admin',
        'password'         => 'Mot de passe admin',
        'email'            => 'Adresse e-mail admin',
        'jellyfin_url'     => 'URL du serveur Jellyfin',
        'jellyfin_api_key'      => 'Clé API Jellyfin',
        'jellyfin_api_key_hint' => 'Jellyfin → Administration → Tableau de bord → Clés d\'API',
        'site_title'            => 'Titre du site',
        'submit'           => 'Créer et continuer',
        'success'          => 'Configuration terminée. Bienvenue !',
        'already_done'     => 'La configuration a déjà été effectuée.',
        'validation' => [
            'username_required'     => 'Le nom d\'utilisateur est requis.',
            'password_required'     => 'Le mot de passe est requis.',
            'password_min'          => 'Le mot de passe doit contenir au moins 8 caractères.',
            'email_required'        => 'L\'adresse e-mail est requise.',
            'email_invalid'         => 'Veuillez saisir une adresse e-mail valide.',
            'jellyfin_url_required' => 'L\'URL Jellyfin est requise.',
            'api_key_required'      => 'La clé API est requise.',
        ],
        'requirements' => [
            'title'       => 'Prérequis système',
            'all_ok'      => 'Tous les prérequis sont satisfaits.',
            'some_fail'   => 'Certains prérequis ne sont pas satisfaits. Corrigez-les avant de continuer.',
            'php'         => 'PHP version ≥ 8.2',
            'pdo_sqlite'  => 'Extension pdo_sqlite',
            'mbstring'    => 'Extension mbstring',
            'openssl'     => 'Extension openssl',
            'data_dir'    => 'Répertoire data/ accessible en écriture',
            'cache_dir'   => 'Répertoire cache/ accessible en écriture',
            'posters_dir' => 'Répertoire cache/posters/ accessible en écriture',
        ],
    ],

    // ── Library ──────────────────────────────────────────────────
    'library' => [
        'title'       => 'Bibliothèque',
        'all'         => 'Tout',
        'movies'      => 'Films',
        'shows'       => 'Séries',
        'movie'       => 'Film',
        'show'        => 'Série',
        'search'      => 'Rechercher…',
        'sort'        => [
            'label'  => 'Trier par',
            'title'  => 'Titre A→Z',
            'year'   => 'Année',
            'added'  => 'Ajouté récemment',
            'rating' => 'Note',
        ],
        'no_results'  => 'Aucun résultat.',
        'empty'       => 'La bibliothèque est vide.',
        'pagination'  => [
            'previous' => 'Précédent',
            'next'     => 'Suivant',
            'of'       => 'sur',
            'items'    => 'éléments',
        ],
    ],

    // ── Movie detail ─────────────────────────────────────────────
    'movie' => [
        'download'    => 'Télécharger',
        'year'        => 'Année',
        'genres'      => 'Genres',
        'rating'      => 'Note',
        'duration'    => 'Durée',
        'synopsis'    => 'Synopsis',
        'no_synopsis' => 'Aucun synopsis disponible.',
    ],

    // ── TV show detail ────────────────────────────────────────────
    'show' => [
        'season_one'  => 'saison',
        'seasons'     => 'saisons',
        'episodes'    => 'épisodes',
        'season'      => 'Saison :number',
        'episode'     => 'Épisode :number',
        'download'    => 'Télécharger',
        'no_episodes' => 'Aucun épisode disponible.',
        'no_seasons'  => 'Aucune saison disponible.',
    ],

    // ── Admin ─────────────────────────────────────────────────────
    'admin' => [
        'title' => 'Panneau d\'administration',
        'nav'   => [
            'dashboard' => 'Tableau de bord',
            'settings'  => 'Paramètres',
            'users'     => 'Utilisateurs',
            'logs'      => 'Journaux de téléchargement',
            'sessions'  => 'Sessions',
        ],
        'dashboard' => [
            'jellyfin_connected'   => 'Connecté',
            'jellyfin_unreachable' => 'Inaccessible',
        ],
        'settings' => [
            'title'              => 'Paramètres',
            'jellyfin'           => 'Jellyfin',
            'jellyfin_url'       => 'URL du serveur Jellyfin',
            'jellyfin_api_key'         => 'Clé API',
            'api_key_replace'          => 'Remplacer',
            'api_key_cancel'           => 'Annuler',
            'api_key_new_placeholder'  => 'Collez la nouvelle clé API',
            'site'               => 'Site',
            'site_title'         => 'Titre du site',
            'public_mode'        => 'Mode public (sans connexion requise)',
            'grid_rows'          => 'Rangées par page',
            'timezone'           => 'Fuseau horaire',
            'poster_cache_days'     => 'Durée du cache des affiches (jours)',
            'session_max_days'      => 'Durée max des sessions (jours)',
            'session_max_days_hint' => '0 = illimité',
            'locale'             => 'Langue par défaut',
            'default_sort'       => 'Tri par défaut',
            'smtp'               => 'Email (SMTP)',
            'smtp_host'          => 'Hôte SMTP',
            'smtp_port'          => 'Port SMTP',
            'smtp_user'          => 'Utilisateur SMTP',
            'smtp_password'      => 'Mot de passe SMTP',
            'smtp_from'              => 'Adresse d\'expédition',

            'test_email' => [
                'title'  => 'Envoyer un e-mail de test',
                'to'     => 'Adresse destinataire',
                'send'   => 'Envoyer le test',
                'notice' => 'Enregistrez vos paramètres SMTP avant d\'envoyer un test.',
            ],
            'allow_password_reset'      => 'Autoriser les utilisateurs à réinitialiser leur mot de passe par e-mail',
            'allow_password_reset_hint' => 'Nécessite que le SMTP soit configuré.',
            'allow_magic_link'          => 'Autoriser la connexion par lien magique (e-mail sans mot de passe)',
            'allow_magic_link_hint'       => 'Nécessite que le SMTP soit configuré.',
            'allow_magic_link_hint_users' => 'Fonctionne uniquement pour les utilisateurs ayant une adresse e-mail enregistrée.',
            'discord'                       => 'Discord',
            'discord_webhook_url'           => 'URL du webhook',
            'discord_webhook_url_placeholder' => 'https://discord.com/api/webhooks/…',
            'discord_notify_downloads'      => 'Publier un message à chaque téléchargement',
            'discord_test_title'            => 'Envoyer un message de test',
            'discord_test_notice'           => 'Enregistrez l\'URL du webhook avant d\'envoyer un test.',
            'discord_test_send'             => 'Envoyer le test',
            'discord_test_sent'             => 'Message de test envoyé sur Discord.',
            'save'               => 'Enregistrer',
            'saved'              => 'Paramètres enregistrés.',
        ],
        'users' => [
            'title'            => 'Utilisateurs',
            'create'           => 'Créer un utilisateur',
            'username'         => 'Nom d\'utilisateur',
            'email'            => 'Adresse e-mail',
            'role'             => 'Rôle',
            'access'           => 'Accès aux contenus',
            'last_activity'    => 'Dernière activité',
            'actions'          => 'Actions',
            'delete'           => 'Supprimer',
            'delete_confirm'   => 'Supprimer cet utilisateur ?',
            'edit'             => 'Modifier',
            'edit_title'       => 'Modifier —',
            'you'              => 'vous',
            'save'             => 'Enregistrer',
            'saved'            => 'Enregistré !',
            'section_settings' => 'Paramètres',
            'roles'          => [
                'admin' => 'Admin',
                'user'  => 'Utilisateur',
            ],
            'access_types' => [
                'movies' => 'Films uniquement',
                'shows'  => 'Séries uniquement',
                'both'   => 'Films et séries',
            ],
            'age_limit'             => 'Limite d\'âge',
            'age_limit_none'        => 'Aucune restriction',
            'age_limit_all'         => 'Tous publics uniquement',
            'age_limit_upto'        => 'Classé :age ans et moins',
            'username_taken'        => 'Ce nom d\'utilisateur est déjà utilisé.',
            'username_invalid'      => 'Le nom d\'utilisateur doit faire 50 caractères maximum et ne pas contenir de caractères de contrôle.',
            'delete_last_admin'     => 'Impossible de supprimer le dernier compte administrateur.',
            'self_role_hint'   => 'Vous ne pouvez pas modifier votre propre rôle.',
            'send_invitation'        => 'Envoyer un e-mail d\'invitation avec les identifiants',
            'force_password_change'  => 'Forcer le changement de mot de passe à la prochaine connexion',
            'generate_password'       => 'Générer',
            'cancel'                => 'Annuler',
            'close'           => 'Fermer',
            'created' => 'Utilisateur créé.',
            'deleted' => 'Utilisateur supprimé.',
            'updated' => 'Utilisateur mis à jour.',
            'reset_password' => [
                'button'     => 'Réinitialiser le mot de passe',
                'self_error' => 'Utilisez votre page de profil pour changer votre propre mot de passe.',
                'done'       => 'Mot de passe réinitialisé.',
                'send_email' => 'Envoyer par e-mail',
                'email_sent' => 'E-mail envoyé à l\'utilisateur.',
                'no_email'   => 'Cet utilisateur n\'a pas d\'adresse e-mail configurée.',
                'no_smtp'    => 'Le SMTP n\'est pas configuré — l\'envoi d\'e-mail est impossible.',
            ],
        ],
        'logs' => [
            'title'         => 'Journaux de téléchargement',
            'user'          => 'Utilisateur',
            'clear'         => 'Effacer les journaux',
            'clear_confirm' => 'Effacer tous les journaux de téléchargement ?',
            'cleared'       => 'Journaux effacés.',
            'empty'         => 'Aucun téléchargement enregistré.',
        ],
        'sessions' => [
            'revoke'         => 'Révoquer toutes les sessions',
            'revoke_confirm' => 'Ceci déconnectera tous les utilisateurs (votre session sera préservée). Continuer ?',
            'revoked'        => 'Toutes les sessions ont été révoquées.',
        ],
    ],

    // ── Errors ────────────────────────────────────────────────────
    'error' => [
        'not_found'           => 'Page introuvable',
        'not_found_detail'    => 'La page demandée n\'existe pas.',
        'server_error'        => 'Erreur serveur',
        'server_error_detail' => 'Une erreur s\'est produite. Veuillez réessayer.',
        'back_home'           => 'Retour à l\'accueil',
        'forbidden'           => 'Accès refusé',
        'forbidden_detail'    => 'Vous n\'avez pas la permission d\'accéder à cette page.',
    ],

    // ── Profile / Mon compte ─────────────────────────────────────
    'profile' => [
        'title' => 'Mon compte',
        'info' => [
            'title' => 'Profil',
            'save'  => 'Mettre à jour',
        ],
        'username' => [
            'title'    => 'Nom d\'utilisateur',
            'save'     => 'Mettre à jour',
            'updated'  => 'Nom d\'utilisateur mis à jour.',
            'taken'    => 'Ce nom d\'utilisateur est déjà pris.',
            'required' => 'Le nom d\'utilisateur est requis.',
            'invalid'  => 'Le nom d\'utilisateur doit faire 50 caractères maximum et ne pas contenir de caractères de contrôle.',
        ],
        'password' => [
            'title'         => 'Modifier le mot de passe',
            'current'       => 'Mot de passe actuel',
            'new'           => 'Nouveau mot de passe',
            'confirm'       => 'Confirmer le nouveau mot de passe',
            'save'          => 'Mettre à jour',
            'success'       => 'Mot de passe mis à jour.',
            'wrong_current' => 'Mot de passe actuel incorrect.',
            'mismatch'      => 'Les mots de passe ne correspondent pas.',
            'too_short'     => 'Le mot de passe doit contenir au moins 8 caractères.',
        ],
        'email' => [
            'title'          => 'Adresse e-mail',
            'current'        => 'Adresse actuelle',
            'placeholder'    => 'nouveau@exemple.com',
            'save'           => 'Mettre à jour',
            'updated'        => 'Adresse e-mail mise à jour.',
            'already_in_use' => 'Cette adresse e-mail est déjà utilisée.',
        ],
        'downloads' => [
            'title' => 'Historique des téléchargements',
            'empty' => 'Aucun téléchargement.',
        ],
        'sessions' => [
            'title'          => 'Sessions actives',
            'current'        => 'Session actuelle',
            'revoke'         => 'Terminer la session',
            'revoke_others'  => 'Terminer toutes les autres sessions',
            'revoked'        => 'Session terminée.',
            'revoked_others' => 'Toutes les autres sessions ont été terminées.',
        ],
        'delete' => [
            'title'      => 'Zone dangereuse',
            'warning'    => 'La suppression de votre compte est définitive et ne peut pas être annulée.',
            'button'     => 'Supprimer mon compte',
            'confirm'    => 'Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.',
            'last_admin' => 'Vous êtes le seul administrateur. Attribuez un autre admin avant de supprimer votre compte.',
        ],
    ],

    // ── Emails ───────────────────────────────────────────────────
    'email' => [
        'footer'         => ':site — ce message est automatique, merci de ne pas y répondre.',
        'greeting'       => 'Bonjour :username,',
        'signin_btn'     => 'Se connecter à :site',
        'label_url'      => 'URL',
        'label_username' => 'Nom d\'utilisateur',
        'label_password' => 'Mot de passe',
        'label_link'     => 'Lien',

        'invitation' => [
            'subject'           => 'Votre compte :site',
            'intro'             => 'Un compte a été créé pour vous sur <strong>:site</strong>. Vous pouvez dès à présent vous connecter et accéder à la médiathèque.',
            'credentials_intro' => 'Vos identifiants :',
        ],

        'password_reset' => [
            'subject'           => 'Votre mot de passe :site a été réinitialisé',
            'subject_self'      => 'Votre mot de passe temporaire :site',
            'intro_admin'       => 'Un administrateur a réinitialisé votre mot de passe sur <strong>:site</strong>. Vous pouvez vous connecter avec le mot de passe temporaire ci-dessous.',
            'intro_self'        => 'Vous avez demandé une réinitialisation de mot de passe sur <strong>:site</strong>. Connectez-vous avec le mot de passe temporaire ci-dessous.',
            'credentials_intro' => 'Vos nouveaux identifiants :',
        ],

        'magic_link' => [
            'subject' => 'Votre lien de connexion pour :site',
            'intro'   => 'Cliquez sur le bouton ci-dessous pour vous connecter à <strong>:site</strong> instantanément. Aucun mot de passe requis.',
            'expires' => 'Ce lien expire dans <strong>15 minutes</strong> et ne peut être utilisé qu\'une seule fois.',
            'ignore'  => 'Si vous n\'avez pas demandé ce lien, vous pouvez ignorer cet e-mail.',
        ],

        'test' => [
            'subject' => 'E-mail de test de :site',
            'intro'   => 'Ceci est un e-mail de test envoyé depuis <strong>:site</strong>.',
            'success' => 'Si vous avez reçu ce message, votre configuration SMTP fonctionne correctement.',
        ],
    ],

    // ── Discord notifications ────────────────────────────────────
    'discord' => [
        'download_message' => '**:user** a téléchargé **:title** (:type)',
        'anonymous'        => 'Anonyme',
        'type_movie'       => 'film',
        'type_episode'     => 'épisode',
    ],

    // ── Download ──────────────────────────────────────────────────
    'download' => [
        'starting'  => 'Votre téléchargement démarre…',
        'forbidden' => 'Vous n\'avez pas la permission de télécharger cet élément.',
        'not_found' => 'Cet élément est introuvable.',
        'logged'    => 'Téléchargement enregistré.',
    ],

    // ── Download history (shared between profile and admin) ───────
    'downloads' => [
        'col_item'    => 'Élément',
        'col_type'    => 'Type',
        'col_date'    => 'Date',
        'type_movie'  => 'Film',
        'type_episode'=> 'Épisode',
    ],

];
