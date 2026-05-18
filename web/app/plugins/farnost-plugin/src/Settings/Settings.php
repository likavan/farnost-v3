<?php

declare(strict_types=1);

namespace Farnost\Plugin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centrálna WP option `farnost_settings` — JSON štruktúra s identitou farnosti,
 * kontaktom, financiami, sociálnymi, brandingom, modulmi (toggly) atď.
 */
final class Settings
{
    public const OPTION_KEY = 'farnost_settings';

    public static function register(): void
    {
        register_setting('farnost', self::OPTION_KEY, [
            'type'         => 'object',
            'show_in_rest' => [
                'schema' => self::schema(),
            ],
            'default'      => self::defaults(),
        ]);
    }

    public static function defaults(): array
    {
        return [
            'identita'   => [
                'nazov'         => '',
                'patrocinium'   => '',
                'dekanat'       => '',
                'dioceza'       => '',
                'rok_zalozenia' => 0,
            ],
            'kontakt'    => [
                'adresa'        => '',
                'telefony'      => [], // pole {popis, cislo}
                'emaily'        => [], // pole {popis, adresa}
                'web'           => '',
                'uradne_hodiny' => '',
            ],
            'financie'   => [
                'iban'        => '',
                'banka'       => '',
                'majitel'     => '',
                'ico'         => '',
            ],
            // Repeater pole [{popis, url}] — admin pridá ľubovoľné siete
            // (FB, IG, YT, X, TikTok, ...). Žiadny mapping na fixed kľúče.
            'socialne'   => [],
            // Externé odkazy v päte (BB diecéza, KBS, Liturgia hodín, ...).
            // GDPR link sa pridáva render-time ako posledný, nie v defaultoch.
            'odkazy'     => [],
            'branding'   => [
                'logo_id'       => 0,
                'primary_color' => '#1e40af',
            ],
            'moduly'     => [
                'oznamy_zapnute'      => true,
                'umysly_zapnute'      => true,
                'rozpis_omsi_zapnuty' => true,
                'zdielanie_zapnute'   => true,
            ],
            'oznamy'     => [
                'publikacny_den'   => 'sunday',
                'publikacny_cas'   => '08:00',
                'dopredne_drafty'  => 2,
            ],
            'upratovanie' => [
                'dalsia_skupina' => 0,
            ],
            'citaty'     => [],
            'setup'      => [
                'completed' => false,
                'completed_at' => '',
            ],
        ];
    }

    public static function get(): array
    {
        $value = get_option(self::OPTION_KEY, []);
        if (!is_array($value)) {
            $value = [];
        }
        return array_replace_recursive(self::defaults(), $value);
    }

    public static function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'identita'    => [
                    'type'       => 'object',
                    'properties' => [
                        'nazov'         => ['type' => 'string'],
                        'patrocinium'   => ['type' => 'string'],
                        'dekanat'       => ['type' => 'string'],
                        'dioceza'       => ['type' => 'string'],
                        'rok_zalozenia' => ['type' => 'integer'],
                    ],
                ],
                'kontakt'     => [
                    'type'       => 'object',
                    'properties' => [
                        'adresa'        => ['type' => 'string'],
                        'telefony'      => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'popis' => ['type' => 'string'],
                                    'cislo' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'emaily'        => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'popis'  => ['type' => 'string'],
                                    'adresa' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'web'           => ['type' => 'string'],
                        'uradne_hodiny' => ['type' => 'string'],
                    ],
                ],
                'financie'    => [
                    'type'       => 'object',
                    'properties' => [
                        'iban'    => ['type' => 'string'],
                        'banka'   => ['type' => 'string'],
                        'majitel' => ['type' => 'string'],
                        'ico'     => ['type' => 'string'],
                    ],
                ],
                'socialne'    => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'popis' => ['type' => 'string'],
                            'url'   => ['type' => 'string'],
                        ],
                    ],
                ],
                'odkazy'      => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'popis' => ['type' => 'string'],
                            'url'   => ['type' => 'string'],
                        ],
                    ],
                ],
                'branding'    => [
                    'type'       => 'object',
                    'properties' => [
                        'logo_id'       => ['type' => 'integer'],
                        'primary_color' => ['type' => 'string'],
                    ],
                ],
                'moduly'      => [
                    'type'       => 'object',
                    'properties' => [
                        'oznamy_zapnute'      => ['type' => 'boolean'],
                        'umysly_zapnute'      => ['type' => 'boolean'],
                        'rozpis_omsi_zapnuty' => ['type' => 'boolean'],
                        'zdielanie_zapnute'   => ['type' => 'boolean'],
                    ],
                ],
                'oznamy'      => [
                    'type'       => 'object',
                    'properties' => [
                        'publikacny_den'  => ['type' => 'string'],
                        'publikacny_cas'  => ['type' => 'string'],
                        'dopredne_drafty' => ['type' => 'integer'],
                    ],
                ],
                'upratovanie' => [
                    'type'       => 'object',
                    'properties' => [
                        'dalsia_skupina' => ['type' => 'integer'],
                    ],
                ],
                'citaty'      => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'text'  => ['type' => 'string'],
                            'autor' => ['type' => 'string'],
                        ],
                    ],
                ],
                'setup'       => [
                    'type'       => 'object',
                    'properties' => [
                        'completed'    => ['type' => 'boolean'],
                        'completed_at' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
}
