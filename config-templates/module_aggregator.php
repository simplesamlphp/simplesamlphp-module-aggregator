<?php

/* Configuration for the aggregator module. */
$config = [
    /* List of aggregators. */
    'aggregators' => [
        'example' => [
            'sources' => [
                ['type' => 'flatfile'],  /* Metadata from metadata-directory. */
                ['type' => 'xml', 'url' => 'https://idp.example.org/Metadata'],
                ['type' => 'xml', 'file' => 'static-metadata.xml'],
            ],
        ],
        'example2' => [
            'sources' => [
                ['type' => 'xml', 'url' => 'https://idp.example.org/Metadata2'],
            ],
            'set' => 'saml2',
            'sign.privatekey' => 'server2.key',
            'sign.certificate' => 'server2.crt',
        ]
    ],

    'maxDuration' => 60*60*24*5, // Maximum 5 days duration on ValidUntil.

    // If base64 encoded for entity is already cached in the entity, should we
    // reconstruct the XML or re-use.
    'reconstruct' => false,

    /* Whether metadata should be signed. */
    'sign.enable' => false,

    /* Private key which should be used when signing the metadata. */
    'sign.privatekey' => 'server.key',

    /* Password to decrypt private key, or NULL if the private key is unencrypted. */
    'sign.privatekey_pass' => null,

    /* Certificate which should be included in the signature. Should correspond to the private key. */
    'sign.certificate' => 'server.crt',
];

