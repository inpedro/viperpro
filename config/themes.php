<?php

/*
|--------------------------------------------------------------------------
| Casino themes (docs/THEMES.md)
|--------------------------------------------------------------------------
| Design tokens per theme. Every value is a fixed hex colour, pixel radius or an
| allowlisted font key: tenants can choose a theme and override primary/accent/
| radius/font, but never inject arbitrary CSS through these settings.
*/

return [
    'default' => 'obsidian',

    'fonts' => [
        'inter'          => ['family' => "'Inter', system-ui, sans-serif", 'google' => 'Inter:wght@400;500;600;700;800'],
        'space-grotesk'  => ['family' => "'Space Grotesk', system-ui, sans-serif", 'google' => 'Space+Grotesk:wght@400;500;600;700'],
        'playfair'       => ['family' => "'Playfair Display', Georgia, serif", 'google' => 'Playfair+Display:wght@500;600;700;800'],
        'manrope'        => ['family' => "'Manrope', system-ui, sans-serif", 'google' => 'Manrope:wght@400;500;600;700;800'],
        'system'         => ['family' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'google' => null],
    ],

    'presets' => [
        'obsidian' => [
            'label' => 'Obsidian', 'description' => 'Dark premium', 'scheme' => 'dark',
            'font' => 'inter', 'heading_font' => 'inter', 'radius' => 14,
            'colors' => [
                'bg' => '#0B0D12', 'surface' => '#12151C', 'surface-2' => '#1A1E27', 'border' => '#262B36',
                'text' => '#F2F4F8', 'muted' => '#9AA3B2', 'primary' => '#7C5CFF', 'primary-contrast' => '#FFFFFF',
                'accent' => '#2DD4BF', 'success' => '#22C55E', 'warning' => '#F59E0B', 'danger' => '#EF4444',
            ],
        ],
        'royal' => [
            'label' => 'Royal', 'description' => 'Luxury casino', 'scheme' => 'dark',
            'font' => 'inter', 'heading_font' => 'playfair', 'radius' => 10,
            'colors' => [
                'bg' => '#0E0A14', 'surface' => '#171021', 'surface-2' => '#21172E', 'border' => '#3A2A4D',
                'text' => '#F6F0E6', 'muted' => '#B9A98F', 'primary' => '#D4AF37', 'primary-contrast' => '#1A1206',
                'accent' => '#A855F7', 'success' => '#4ADE80', 'warning' => '#FBBF24', 'danger' => '#F87171',
            ],
        ],
        'minimal' => [
            'label' => 'Minimal', 'description' => 'Clean modern', 'scheme' => 'light',
            'font' => 'manrope', 'heading_font' => 'manrope', 'radius' => 12,
            'colors' => [
                'bg' => '#F7F8FA', 'surface' => '#FFFFFF', 'surface-2' => '#F0F2F5', 'border' => '#E3E6EB',
                'text' => '#12151C', 'muted' => '#5B6472', 'primary' => '#111827', 'primary-contrast' => '#FFFFFF',
                'accent' => '#2563EB', 'success' => '#16A34A', 'warning' => '#D97706', 'danger' => '#DC2626',
            ],
        ],
        'neon' => [
            'label' => 'Neon', 'description' => 'Modern gaming', 'scheme' => 'dark',
            'font' => 'space-grotesk', 'heading_font' => 'space-grotesk', 'radius' => 16,
            'colors' => [
                'bg' => '#07070D', 'surface' => '#0F0F1A', 'surface-2' => '#171728', 'border' => '#2A2A45',
                'text' => '#EDEBFF', 'muted' => '#8E8BB3', 'primary' => '#00F0FF', 'primary-contrast' => '#001014',
                'accent' => '#FF2BD6', 'success' => '#39FF88', 'warning' => '#FFD60A', 'danger' => '#FF4D6D',
            ],
        ],
    ],
];
