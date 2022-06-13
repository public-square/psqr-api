<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')

    ->notPath('Transport/Connection.php')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration' => true,
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal',
        ],
        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'types_spaces' => [
            'space' => 'single',
        ],
        'array_indentation' => true,
        'yoda_style' => false,
        'align_multiline_comment' => false,
        'phpdoc_trim_consecutive_blank_line_separation' => false,
    ])
    ->setFinder($finder)
;
