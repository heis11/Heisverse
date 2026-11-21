<?php
/**
 * HEISVERSE — 300 Unique Themes Generator
 * Produces exactly 300 distinct themes across 20 categories (15 per category).
 */

$categories = [
    'Cinematic' => [
        'prefix' => 'cine',
        'baseHue' => 210,
        'names' => ['Midnight Premiere', 'Noir Shadow', 'Velvet Curtain', 'IMAX Horizon', 'Directors Cut', 'Silver Screen', 'Celluloid Classic', 'Cannes Gold', 'Oscars Night', 'Technicolor 70', 'Film Noir 40', 'Auteur Vision', 'Studio 54 Film', 'Anamorphic Blue', 'Panavision Glow']
    ],
    'Dark Premium' => [
        'prefix' => 'dark',
        'baseHue' => 220,
        'names' => ['Obsidian Elite', 'Carbon Fiber', 'Onyx Black', 'Titanium Dark', 'Slate Phantom', 'Ebony Luxe', 'Shadow Velvet', 'Midnight Onyx', 'Abyssal Dark', 'Basalt Stone', 'Jet Stream', 'Dark Matter', 'Graphite Steel', 'Charcoal Frost', 'Eclipse Crown']
    ],
    'Galaxy' => [
        'prefix' => 'gal',
        'baseHue' => 270,
        'names' => ['Andromeda Mist', 'Milky Way Drift', 'Cosmic Dust', 'Spiral Arm', 'Supernova Spark', 'Interstellar Ray', 'Quasar Beam', 'Pulsar Flash', 'Hubble Deep', 'Starlight Veil', 'Orion Belt', 'Centaurus Glow', 'Galactic Core', 'Nebular Ring', 'Celestial Stream']
    ],
    'Space' => [
        'prefix' => 'space',
        'baseHue' => 240,
        'names' => ['Apollo Orbit', 'Lunar Darkside', 'Martian Rust', 'Jupiter Eye', 'Saturn Rings', 'Neptune Deep', 'Pluto Frost', 'Voyager Path', 'Deep Vacuum', 'Asteroid Belt', 'Solar Flare', 'Cosmic Void', 'Kepler Exoplanet', 'Titan Atmosphere', 'Zero Gravity']
    ],
    'Nature' => [
        'prefix' => 'nat',
        'baseHue' => 140,
        'names' => ['Amazon Canopy', 'Redwood Forest', 'Alpine Moss', 'Bamboo Grove', 'Cedar Ridge', 'Evergreen Wild', 'Fern Glade', 'Highland Valley', 'Jungle Mist', 'Mangrove Sanctuary', 'Old Oak', 'Pine Needle', 'Rainforest Rain', 'Tropical Flora', 'Wild Wood']
    ],
    'Sky and Clouds' => [
        'prefix' => 'sky',
        'baseHue' => 200,
        'names' => ['Cumulus Dream', 'Cirrus Feather', 'Stratus Veil', 'Morning Azure', 'High Noon Sky', 'Twilight Altitude', 'Ozone Blue', 'Storm Cloud Peak', 'Cloud Nine', 'Gentle Breeze', 'Skyline Horizon', 'Vapour Trail', 'Zephyr Light', 'Overcast Silver', 'Nimbus Echo']
    ],
    'Ocean' => [
        'prefix' => 'oce',
        'baseHue' => 190,
        'names' => ['Mariana Trench', 'Coral Reef', 'Pacific Swell', 'Atlantic Mist', 'Arctic Iceberg', 'Bioluminescence', 'Coastal Foam', 'Deep Trench', 'Lagoon Waters', 'Nautilus Deep', 'Poseidon Trident', 'Sea Glass', 'Tidal Wave', 'Whale Song', 'Sunken Galleon']
    ],
    'Neon' => [
        'prefix' => 'neon',
        'baseHue' => 310,
        'names' => ['Tokyo Street', 'Miami Vice', 'Laser Highway', 'Electric Pulse', 'Synthwave Glow', 'Arcade Nights', 'Fluorescent Pop', 'Vapor Neon', 'Neon Citrus', 'Ultraviolet Beam', 'Glowstick Green', 'Laser Pink', 'Cobalt Lightning', 'Krypton Flash', 'Radical Magenta']
    ],
    'Cyberpunk' => [
        'prefix' => 'cyb',
        'baseHue' => 180,
        'names' => ['Neo Shinjuku', 'Matrix Code', 'Augmented Reality', 'Cyber Glitch', 'High Tech Low Life', 'Neural Link', 'Megacity 2099', 'Chrome Shadow', 'Netrunner Ice', 'Hacker Terminal', 'Silicon Valley Dark', 'Cyborg Heart', 'Grid Overload', 'Quantum Key', 'Android Dream']
    ],
    'Luxury' => [
        'prefix' => 'lux',
        'baseHue' => 45,
        'names' => ['Imperial Gold', 'Champagne Fizz', 'Monaco Casino', 'Diamond Crest', 'Platinum Crown', 'Rose Gold Satin', 'Royal Velvet', 'Silk & Cashmere', 'Penthouse Suite', 'Gilded Arch', 'Caviar Luxe', 'Emerald Palace', 'Sapphire Vault', 'Bespoke Tuxedo', 'Private Jet']
    ],
    'Minimal' => [
        'prefix' => 'min',
        'baseHue' => 0,
        'names' => ['Pure Monolith', 'Clean Slate', 'Studio White', 'Nordic Timber', 'Soft Fog', 'Concrete Modern', 'Paper Texture', 'Bauhaus Grid', 'Zen Stone', 'Monochrome Line', 'Neutral Gray', 'Bare Canvas', 'Essential Space', 'Simple Contrast', 'Quiet Tone']
    ],
    'Modern' => [
        'prefix' => 'mod',
        'baseHue' => 215,
        'names' => ['SaaS Indigo', 'Fintech Cobalt', 'Glassmorphism Flow', 'Metric Blue', 'Studio Interface', 'Precision Crisp', 'Urban Glass', 'Digital Native', 'Vector Dynamic', 'Flat Vibrant', 'Modern Acrylic', 'Fluid UI', 'NextGen Dark', 'Pro Canvas', 'Hyperion Clean']
    ],
    'Light' => [
        'prefix' => 'light',
        'baseHue' => 210,
        'names' => ['Daylight Ceramic', 'Alabaster Breeze', 'Morning Milk', 'Crisp Cotton', 'Linen White', 'Porcelain Sheen', 'Parchment Glow', 'Pearl Shimmer', 'Vanilla Cloud', 'Birch Bark', 'Chalkboard Dust', 'Opal Lustre', 'Ivory Coast', 'Snowdrift Crest', 'Dawn Radiance']
    ],
    'Purple' => [
        'prefix' => 'purp',
        'baseHue' => 280,
        'names' => ['Royal Amethyst', 'Deep Plum', 'Violet Dusk', 'Lavender Fields', 'Orchid Bloom', 'Mulberry Wine', 'Grape Soda', 'Heather Moor', 'Iris Petal', 'Boysenberry Jam', 'Purple Haze', 'Magenta Reign', 'Lilac Velvet', 'Aubergine Silk', 'Heliotrope Glow']
    ],
    'Blue' => [
        'prefix' => 'blue',
        'baseHue' => 225,
        'names' => ['Sapphire Royale', 'Cobalt Surge', 'Deep Navy', 'Steel Azure', 'Cerulean Splash', 'Lapis Lazuli', 'Prussian Depths', 'Electric Aegean', 'Denim Wash', 'Skyline Blue', 'Indigo Ink', 'Glacier Frost', 'Cornflower Field', 'Midnight Harbor', 'Teal Sapphire']
    ],
    'Red' => [
        'prefix' => 'red',
        'baseHue' => 350,
        'names' => ['Crimson Cinema', 'Ruby Fire', 'Scarlet Velvet', 'Blood Orange', 'Burgundy Wine', 'Cherry Pop', 'Garnet Gem', 'Cardinals Robe', 'Red Lantern', 'Dragon Ember', 'Volcano Magma', 'Mars Rock', 'Brick Wall', 'Rose Petal', 'Campfire Flame']
    ],
    'Green' => [
        'prefix' => 'grn',
        'baseHue' => 125,
        'names' => ['Emerald Crown', 'Jade Dynasty', 'Mint Crisp', 'Forest Pine', 'Lime Zest', 'Sage Leaf', 'Olive Orchard', 'Malachite Stone', 'Mossy Glen', 'Clover Patch', 'Green Apple', 'Basil Herb', 'Pistachio Creme', 'Seaweed Depths', 'Matcha Latte']
    ],
    'Sunset' => [
        'prefix' => 'sun',
        'baseHue' => 25,
        'names' => ['Golden Hour', 'Amber Horizon', 'Tuscan Twilight', 'Papaya Sunset', 'Apricot Sky', 'Copper Sun', 'Saffron Dusk', 'Desert Mirage', 'Sunburst Warmth', 'Alpenglow Peak', 'Peach Melba', 'Campari Orange', 'Evening Blaze', 'Solstice Glow', 'Sundown Ember']
    ],
    'Aurora' => [
        'prefix' => 'aur',
        'baseHue' => 165,
        'names' => ['Borealis Green', 'Australis Violet', 'Polar Light', 'Magnetic Dance', 'Nordic Shimmer', 'Arctic Curtain', 'Solar Wind', 'Tromso Sky', 'Reykjavik Glow', 'Lofoten Night', 'Spectra Wave', 'Green Phosphor', 'Aurora Corona', 'Ethereal Ribbon', 'Subzero Spark']
    ],
    'Retro' => [
        'prefix' => 'ret',
        'baseHue' => 35,
        'names' => ['VHS 1984', 'Cassette Tape', 'Arcade 1989', 'Floppy Disk', 'CRT Scanline', 'Sepia Photo', '80s Neon Wave', 'Polaroid Print', '70s Mustard', 'Vinyl Record', 'Commodore 64', 'Atari 2600', 'Retro Boombox', 'Drive-In Movie', 'Vintage Poster']
    ]
];

$themes = [];
$totalCount = 0;
$seenIds = [];

foreach ($categories as $catName => $catData) {
    $prefix = $catData['prefix'];
    $baseHue = $catData['baseHue'];
    $names = $catData['names'];

    for ($i = 0; $i < 15; $i++) {
        $num = $i + 1;
        $id = sprintf('%s-%02d', $prefix, $num);
        if (isset($seenIds[$id])) {
            die("Duplicate ID found: $id\n");
        }
        $seenIds[$id] = true;

        $name = $names[$i];
        $isLight = ($catName === 'Light');

        // Calculate dynamic harmonized palette
        $hueShift = ($i * 7) % 360;
        $activeHue = ($baseHue + $hueShift) % 360;

        if ($isLight) {
            $bgPrimary = sprintf('hsl(%d, 20%%, 97%%)', $activeHue);
            $bgSecondary = sprintf('hsl(%d, 25%%, 93%%)', $activeHue);
            $bgGlass = sprintf('hsla(%d, 25%%, 98%%, 0.88)', $activeHue);
            $borderGlass = 'rgba(0, 0, 0, 0.08)';
            $textMain = '#0f172a';
            $textMuted = '#475569';
            $textDim = '#64748b';
            $accent = sprintf('hsl(%d, 80%%, 45%%)', $activeHue);
            $accentHover = sprintf('hsl(%d, 85%%, 38%%)', $activeHue);
            $accentGlow = sprintf('hsla(%d, 80%%, 45%%, 0.25)', $activeHue);
            $skyGradient = sprintf('linear-gradient(180deg, hsl(%d, 60%%, 92%%) 0%%, hsl(%d, 20%%, 97%%) 100%%)', $activeHue, $activeHue);
        } else {
            $bgPrimary = sprintf('hsl(%d, 25%%, %d%%)', $activeHue, 4 + ($i % 3));
            $bgSecondary = sprintf('hsl(%d, 28%%, %d%%)', $activeHue, 8 + ($i % 4));
            $bgGlass = sprintf('hsla(%d, 28%%, 12%%, 0.82)', $activeHue);
            $borderGlass = sprintf('hsla(%d, 50%%, 70%%, 0.12)', $activeHue);
            $textMain = '#f8fafc';
            $textMuted = sprintf('hsl(%d, 35%%, 75%%)', $activeHue);
            $textDim = sprintf('hsl(%d, 20%%, 50%%)', $activeHue);
            $accent = sprintf('hsl(%d, 85%%, 58%%)', $activeHue);
            $accentHover = sprintf('hsl(%d, 90%%, 48%%)', $activeHue);
            $accentGlow = sprintf('hsla(%d, 85%%, 58%%, 0.35)', $activeHue);
            $skyGradient = sprintf('radial-gradient(circle at 50%% 0%%, hsl(%d, 45%%, 18%%) 0%%, hsl(%d, 25%%, %d%%) 85%%)', $activeHue, $activeHue, 4 + ($i % 3));
        }

        $themes[] = [
            'id' => $id,
            'name' => $name,
            'category' => $catName,
            'isLight' => $isLight,
            'bgPrimary' => $bgPrimary,
            'bgSecondary' => $bgSecondary,
            'bgGlass' => $bgGlass,
            'borderGlass' => $borderGlass,
            'textMain' => $textMain,
            'textMuted' => $textMuted,
            'textDim' => $textDim,
            'accent' => $accent,
            'accentHover' => $accentHover,
            'accentGlow' => $accentGlow,
            'skyGradient' => $skyGradient
        ];
        $totalCount++;
    }
}

$outputFile = __DIR__ . '/../assets/data/themes-300.json';
file_put_contents($outputFile, json_encode($themes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "TOTAL_THEMES_GENERATED: {$totalCount}\n";
echo "CATEGORIES_COUNT: " . count($categories) . "\n";
echo "FILE_SAVED: {$outputFile}\n";
