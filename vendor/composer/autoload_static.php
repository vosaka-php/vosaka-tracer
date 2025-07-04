<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit12bf7f4099f074d934b0e5ed71ead280
{
    public static $prefixLengthsPsr4 = array (
        'v' => 
        array (
            'vosaka\\vtracer\\' => 15,
            'venndev\\vosaka\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'vosaka\\vtracer\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/vosaka/vtracer',
        ),
        'venndev\\vosaka\\' => 
        array (
            0 => __DIR__ . '/..' . '/venndev/v-osaka/src/venndev/vosaka',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit12bf7f4099f074d934b0e5ed71ead280::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit12bf7f4099f074d934b0e5ed71ead280::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit12bf7f4099f074d934b0e5ed71ead280::$classMap;

        }, null, ClassLoader::class);
    }
}
