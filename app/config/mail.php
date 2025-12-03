<?php
return [
    'driver' => 'smtp',
    'host' => 'smtp.mailtrap.io',
    'port' => 2525,
    'from' => [
        'address' => 'noreply@ejemplo.com',
        'name' => 'Sistema de Gestión'
    ],
    'encryption' => null,
    'username' => '',
    'password' => '',
    'sendmail' => '/usr/sbin/sendmail -bs',
    'pretend' => false,
    'timeout' => 30,
    'auth' => true,
    'smtp' => [
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => '',
        'password' => '',
        'encryption' => null
    ],
    'mailgun' => [
        'domain' => '',
        'secret' => ''
    ],
    'ses' => [
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1'
    ],
    'sparkpost' => [
        'secret' => ''
    ]
];