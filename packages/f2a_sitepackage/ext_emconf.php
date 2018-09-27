<?php

/**
 * Extension Manager/Repository config file for ext "f2a_sitepackage".
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'F2a Sitepackage',
    'description' => '',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99',
            'rte_ckeditor' => '8.7.0-9.5.99',
            'bootstrap_package' => '9.1.0-9.1.99'
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'IHaveNoCompany\\F2aSitepackage\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'F2a',
    'author_email' => 'nomail@nodomain.com',
    'author_company' => 'I have no company',
    'version' => '1.0.0',
];
