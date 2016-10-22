# yii2-acme


[![Latest Version](https://img.shields.io/github/tag/sam002/yii2-acme.svg?style=flat-square&label=releas)](https://github.com/sam002/yii2-acme/tags)
[![Software License](https://img.shields.io/badge/license-LGPL3-brightgreen.svg?style=flat-square)](LICENSE.md)

YII2 extension for certificate management using ACME (Automatic Certificate Management Environment)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require sam002/yii2-acme:~0.1.2
```
or add

```json
"sam002/yii2-acme" : "~0.1.2"
```

to the require section of your application's `composer.json` file.


Usage
-----

After extension is installed you need to setup auth client collection application component:

**Configure**

Frontend (need to checked by certificate provider)

```php
...
'module' => [
    //Catch all requests on .well-known
    '.well-known' => [
        'class' => 'sam002\acme\Acme',
        //optional
        'location' => realpath('../runtime/acme'),
        'providerUrl' => Acme::PROVIDERS['letsencrypt:production']
        'keyLength' => 2048,
        'keyStorage' => 'sam002\acme\storage\file\KeyStorageFile',
        'certificateStorage' => 'sam002\acme\storage\file\CertificateStorageFile'
        'challengeStorage' => 'sam002\acme\storage\file\ChallengeStorageFile'
    ],
    ...
]
```

Console task

```php
...
'controllerMap' => [
    'acme' => [
        'class' => 'sam002\acme\console\AcmeController'
    ],
    ...
]
```


Further Information
-------------------
- [ACME](https://ietf-wg-acme.github.io/acme/)
- [ACME library](https://github.com/kelunik/acme)


Credits
-------

- [sam002](https://github.com/sam002)
- [All Contributors](../../contributors)


License
-------

The LGPLv3 License. Please see [License File](LICENSE) for more information.
