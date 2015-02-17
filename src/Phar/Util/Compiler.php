<?php

namespace Phar\Util;

use Symfony\Component\Finder\Finder;


class Compiler
{
    public function compile($pharFile, $namePhar, $dirLib, $autoload)
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $phar = new \Phar($pharFile, 0, $namePhar);
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $root = __DIR__.'/../../../vendor/';

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->notName('swiftmailer_generate_mimes_config.php')
            ->in($root . $dirLib)
        ;

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        // Stubs
        $phar->setStub($this->getStub($namePhar, $autoload));

        $phar->stopBuffering();

        unset($phar);
    }

    protected function addFile(\Phar $phar, \SplFileInfo $file, $strip = true)
    {
        $path = str_replace(dirname(dirname(dirname(__DIR__))).DIRECTORY_SEPARATOR, '', $file->getRealPath());

        $content = file_get_contents($file);
        if ($strip) {
            $content = self::stripWhitespace($content);
        }

        $phar->addFromString($path, $content);
    }

    protected function getStub($namePhar, $autoload)
    {
        return <<<"EOF"
<?php
/*
 * This source file is subject to the MIT license.
 */
Phar::mapPhar('$namePhar');

require_once 'phar://$namePhar/$autoload';

__HALT_COMPILER();
EOF;
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * Based on Kernel::stripComments(), but keeps line numbers intact.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the whitespace removed
     */
    public static function stripWhitespace($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }
}
