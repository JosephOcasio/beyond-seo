x<?php

use Doctrine\Common\Proxy\AbstractProxyFactory;

// Mandatory Read!!! https://www.doctrine-project.org/projects/doctrine-orm/en/2.13/reference/advanced-configuration.html
return [
    'models' => [
        'db' => [
            'baseDirectory' => APP_ROOT_DIR . '/src/Domain/Common/Repo/DB/Models/',
            'modelNameSpace' => 'App\\Domain\\Common\\Repo\\DB\\Models',
            'dbTablePrefix' => 'Entity',
            'modelNamePrefix' => 'DB'
        ],
        'legacyDB' => [
            'baseDirectory' => APP_ROOT_DIR . '/src/Domain/Common/Repo/LegacyDB/Models/',
            'modelNameSpace' => 'App\\Domain\\Common\\Repo\\LegacyDB\\Models',
            'modelNamePrefix' => 'LegacyDB',
            'dbTablePrefix' => '',
            'kohanaOrmModels' => [
                'user_milestone' => true,
                'category' => true,
                'role' => true,
                'ad_account' => true,
                'keyword_site' => true,
                'user' => true,
                'city' => true,
                'site_competitor' => true,
                'country' => true,
                'keyword' => true,
                'site' => true,
                'api_user' => true,
                'reseller' => true,
                'business_listing' => true,
                'business_listing_country' => true,
                'site_setting' => true,
                'listingservice' => true,
                'site_category' => true,
                'site_gmb_category' => true,
                'site_photo' => true,
                'feature' => true,
                'subscription_feature' => true,
                'subscription_upgrade' => true,
                'subscription' => true,
                'user_subscription_site' => true,
                'user_subscription' => true,
                'domain' => true,
                'domain_info' => true,
                'roles_user' => true,
                'user_site' => true,
                'gmb_category' => true,
                'gmb_category_category' => true,
                'gmb_category_country' => true,
                'translation' => true,
                'user_setting' => true,
                'viewtranslation_key' => true,
                'viewtranslation_value' => true,
                'viewtranslation_default_term' => true
            ]
        ]
    ]
];