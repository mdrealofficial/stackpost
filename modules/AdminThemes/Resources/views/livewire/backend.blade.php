    @php
        $backendLightColorKeys = ['accent_color', 'sidebar_bg_color', 'header_bg_color', 'header_active_color', 'link_color', 'link_hover_color', 'border_color', 'muted_text_color', 'sidebar_text_color', 'header_text_color', 'success_color', 'warning_color', 'danger_color'];
        $backendDarkColorKeys = ['dark_accent_color', 'dark_sidebar_bg_color', 'dark_header_bg_color', 'dark_header_active_color', 'dark_link_color', 'dark_link_hover_color', 'dark_border_color', 'dark_muted_text_color', 'dark_sidebar_text_color', 'dark_header_text_color', 'dark_success_color', 'dark_warning_color', 'dark_danger_color'];
        $backendTypographyKeys = ['font_family'];
        $backendComponentKeys = ['card_radius', 'input_radius', 'button_radius', 'button_style', 'button_shadow'];
        $backendLayoutKeys = ['layout_width', 'page_max_width', 'supports_dark_mode', 'allow_user_appearance_toggle', 'default_appearance', 'density', 'section_spacing', 'preview_mode'];
        $backendAdvancedKeys = ['custom_css', 'custom_js'];
        $fontStacks = [
            'inter' => '"Inter", ui-sans-serif, system-ui, sans-serif',
            'instrument-sans' => '"Instrument Sans", ui-sans-serif, system-ui, sans-serif',
            'plus-jakarta-sans' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif',
            'manrope' => '"Manrope", ui-sans-serif, system-ui, sans-serif',
            'outfit' => '"Outfit", ui-sans-serif, system-ui, sans-serif',
            'sora' => '"Sora", ui-sans-serif, system-ui, sans-serif',
            'space-grotesk' => '"Space Grotesk", ui-sans-serif, system-ui, sans-serif',
            'public-sans' => '"Public Sans", ui-sans-serif, system-ui, sans-serif',
            'ibm-plex-sans' => '"IBM Plex Sans", ui-sans-serif, system-ui, sans-serif',
            'dm-sans' => '"DM Sans", ui-sans-serif, system-ui, sans-serif',
            'system' => 'ui-sans-serif, system-ui, sans-serif',
        ];

        $backendPreviewState = [
            'accent_color' => old('backend_settings.accent_color', $backendThemeValues['accent_color'] ?? ($backendThemeSchema['accent_color']['default'] ?? '#4f46e5')),
            'sidebar_bg_color' => old('backend_settings.sidebar_bg_color', $backendThemeValues['sidebar_bg_color'] ?? ($backendThemeSchema['sidebar_bg_color']['default'] ?? '#f5f7fb')),
            'header_bg_color' => old('backend_settings.header_bg_color', $backendThemeValues['header_bg_color'] ?? ($backendThemeSchema['header_bg_color']['default'] ?? '#ffffff')),
            'header_active_color' => old('backend_settings.header_active_color', $backendThemeValues['header_active_color'] ?? ($backendThemeSchema['header_active_color']['default'] ?? '#0f172a')),
            'link_color' => old('backend_settings.link_color', $backendThemeValues['link_color'] ?? ($backendThemeSchema['link_color']['default'] ?? '#4f46e5')),
            'link_hover_color' => old('backend_settings.link_hover_color', $backendThemeValues['link_hover_color'] ?? ($backendThemeSchema['link_hover_color']['default'] ?? '#4338ca')),
            'border_color' => old('backend_settings.border_color', $backendThemeValues['border_color'] ?? ($backendThemeSchema['border_color']['default'] ?? '#cbd5e1')),
            'muted_text_color' => old('backend_settings.muted_text_color', $backendThemeValues['muted_text_color'] ?? ($backendThemeSchema['muted_text_color']['default'] ?? '#64748b')),
            'sidebar_text_color' => old('backend_settings.sidebar_text_color', $backendThemeValues['sidebar_text_color'] ?? ($backendThemeSchema['sidebar_text_color']['default'] ?? '#475569')),
            'header_text_color' => old('backend_settings.header_text_color', $backendThemeValues['header_text_color'] ?? ($backendThemeSchema['header_text_color']['default'] ?? '#0f172a')),
            'success_color' => old('backend_settings.success_color', $backendThemeValues['success_color'] ?? ($backendThemeSchema['success_color']['default'] ?? '#059669')),
            'warning_color' => old('backend_settings.warning_color', $backendThemeValues['warning_color'] ?? ($backendThemeSchema['warning_color']['default'] ?? '#d97706')),
            'danger_color' => old('backend_settings.danger_color', $backendThemeValues['danger_color'] ?? ($backendThemeSchema['danger_color']['default'] ?? '#dc2626')),
            'dark_accent_color' => old('backend_settings.dark_accent_color', $backendThemeValues['dark_accent_color'] ?? ($backendThemeSchema['dark_accent_color']['default'] ?? '#818cf8')),
            'dark_sidebar_bg_color' => old('backend_settings.dark_sidebar_bg_color', $backendThemeValues['dark_sidebar_bg_color'] ?? ($backendThemeSchema['dark_sidebar_bg_color']['default'] ?? '#0d131c')),
            'dark_header_bg_color' => old('backend_settings.dark_header_bg_color', $backendThemeValues['dark_header_bg_color'] ?? ($backendThemeSchema['dark_header_bg_color']['default'] ?? '#111827')),
            'dark_header_active_color' => old('backend_settings.dark_header_active_color', $backendThemeValues['dark_header_active_color'] ?? ($backendThemeSchema['dark_header_active_color']['default'] ?? '#f8fafc')),
            'dark_link_color' => old('backend_settings.dark_link_color', $backendThemeValues['dark_link_color'] ?? ($backendThemeSchema['dark_link_color']['default'] ?? '#a5b4fc')),
            'dark_link_hover_color' => old('backend_settings.dark_link_hover_color', $backendThemeValues['dark_link_hover_color'] ?? ($backendThemeSchema['dark_link_hover_color']['default'] ?? '#c7d2fe')),
            'dark_border_color' => old('backend_settings.dark_border_color', $backendThemeValues['dark_border_color'] ?? ($backendThemeSchema['dark_border_color']['default'] ?? '#334155')),
            'dark_muted_text_color' => old('backend_settings.dark_muted_text_color', $backendThemeValues['dark_muted_text_color'] ?? ($backendThemeSchema['dark_muted_text_color']['default'] ?? '#94a3b8')),
            'dark_sidebar_text_color' => old('backend_settings.dark_sidebar_text_color', $backendThemeValues['dark_sidebar_text_color'] ?? ($backendThemeSchema['dark_sidebar_text_color']['default'] ?? '#cbd5e1')),
            'dark_header_text_color' => old('backend_settings.dark_header_text_color', $backendThemeValues['dark_header_text_color'] ?? ($backendThemeSchema['dark_header_text_color']['default'] ?? '#f8fafc')),
            'dark_success_color' => old('backend_settings.dark_success_color', $backendThemeValues['dark_success_color'] ?? ($backendThemeSchema['dark_success_color']['default'] ?? '#34d399')),
            'dark_warning_color' => old('backend_settings.dark_warning_color', $backendThemeValues['dark_warning_color'] ?? ($backendThemeSchema['dark_warning_color']['default'] ?? '#f59e0b')),
            'dark_danger_color' => old('backend_settings.dark_danger_color', $backendThemeValues['dark_danger_color'] ?? ($backendThemeSchema['dark_danger_color']['default'] ?? '#f87171')),
            'font_family' => old('backend_settings.font_family', $backendThemeValues['font_family'] ?? ($backendThemeSchema['font_family']['default'] ?? 'inter')),
            'layout_width' => old('backend_settings.layout_width', $backendThemeValues['layout_width'] ?? ($backendThemeSchema['layout_width']['default'] ?? 'full')),
            'page_max_width' => old('backend_settings.page_max_width', $backendThemeValues['page_max_width'] ?? ($backendThemeSchema['page_max_width']['default'] ?? '90rem')),
            'supports_dark_mode' => old('backend_settings.supports_dark_mode', $backendThemeValues['supports_dark_mode'] ?? ($backendThemeSchema['supports_dark_mode']['default'] ?? '1')),
            'allow_user_appearance_toggle' => old('backend_settings.allow_user_appearance_toggle', $backendThemeValues['allow_user_appearance_toggle'] ?? ($backendThemeSchema['allow_user_appearance_toggle']['default'] ?? '1')),
            'default_appearance' => old('backend_settings.default_appearance', $backendThemeValues['default_appearance'] ?? ($backendThemeSchema['default_appearance']['default'] ?? ($backendThemeValues['appearance'] ?? 'system'))),
            'density' => old('backend_settings.density', $backendThemeValues['density'] ?? ($backendThemeSchema['density']['default'] ?? 'comfortable')),
            'section_spacing' => old('backend_settings.section_spacing', $backendThemeValues['section_spacing'] ?? ($backendThemeSchema['section_spacing']['default'] ?? '1.5rem')),
            'preview_mode' => old('backend_settings.preview_mode', $backendThemeValues['preview_mode'] ?? ($backendThemeSchema['preview_mode']['default'] ?? 'desktop')),
        ];

        $backendColorPresets = [
            'accent_color' => ['#1d4ed8', '#0f766e', '#7c3aed', '#c2410c', '#be123c', '#111827'],
            'sidebar_bg_color' => ['#ffffff', '#fcfcfd', '#f9fafb', '#f5f5f5', '#f3f4f6', '#eef1f4'],
            'header_bg_color' => ['#fffdf8', '#ffffff', '#fbfcfa', '#fcfbff', '#fffaf5', '#ffffff'],
            'header_active_color' => ['#0f172a', '#1e3a8a', '#14532d', '#581c87', '#7c2d12', '#111827'],
            'link_color' => ['#1d4ed8', '#0f766e', '#7c3aed', '#c2410c', '#be123c', '#0f172a'],
            'link_hover_color' => ['#1e40af', '#115e59', '#6d28d9', '#9a3412', '#9f1239', '#020617'],
            'border_color' => ['#efefef', '#eceff3', '#edf1ed', '#f0edfa', '#f4eeea', '#e9edf1'],
            'muted_text_color' => ['#6b7280', '#64748b', '#667564', '#6b7280', '#8a6f61', '#6b7280'],
            'sidebar_text_color' => ['#44403c', '#334155', '#3f4b3c', '#4c1d95', '#7c2d12', '#374151'],
            'header_text_color' => ['#1c1917', '#0f172a', '#1f2937', '#3b0764', '#431407', '#111827'],
            'success_color' => ['#15803d', '#0f766e', '#166534', '#2f855a', '#0f766e', '#15803d'],
            'warning_color' => ['#c2410c', '#d97706', '#ca8a04', '#d97706', '#ea580c', '#d97706'],
            'danger_color' => ['#b91c1c', '#dc2626', '#be123c', '#c53030', '#be123c', '#dc2626'],
            'dark_accent_color' => ['#818cf8', '#2dd4bf', '#a78bfa', '#fb923c', '#fb7185', '#e5e7eb'],
            'dark_sidebar_bg_color' => ['#111827', '#0f172a', '#10231c', '#1e1b4b', '#2b1711', '#111827'],
            'dark_header_bg_color' => ['#172033', '#111827', '#13221d', '#22163f', '#2c1913', '#0f172a'],
            'dark_header_active_color' => ['#f8fafc', '#dbeafe', '#dcfce7', '#ede9fe', '#ffedd5', '#f9fafb'],
            'dark_link_color' => ['#93c5fd', '#5eead4', '#c4b5fd', '#fdba74', '#fda4af', '#e5e7eb'],
            'dark_link_hover_color' => ['#bfdbfe', '#99f6e4', '#ddd6fe', '#fed7aa', '#fecdd3', '#f8fafc'],
            'dark_border_color' => ['#334155', '#3b475b', '#35504a', '#4c3f73', '#5b4638', '#3f3f46'],
            'dark_muted_text_color' => ['#94a3b8', '#94a3b8', '#9fb4aa', '#b4a7d6', '#c2a89a', '#9ca3af'],
            'dark_sidebar_text_color' => ['#cbd5e1', '#cbd5e1', '#d1fae5', '#ddd6fe', '#fed7aa', '#d1d5db'],
            'dark_header_text_color' => ['#f8fafc', '#f8fafc', '#f0fdf4', '#f5f3ff', '#fff7ed', '#f9fafb'],
            'dark_success_color' => ['#34d399', '#2dd4bf', '#4ade80', '#48bb78', '#2dd4bf', '#34d399'],
            'dark_warning_color' => ['#fb923c', '#f59e0b', '#facc15', '#f59e0b', '#fb923c', '#f59e0b'],
            'dark_danger_color' => ['#f87171', '#f87171', '#fb7185', '#fc8181', '#fb7185', '#f87171'],
        ];

        $backendLibraryTags = collect($appThemes)
            ->flatMap(fn ($theme) => is_array($theme->meta['tags'] ?? null) ? $theme->meta['tags'] : [])
            ->map(fn ($tag) => (string) $tag)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $backendPalettePresets = [
            [
                'key' => 'linen-ledger',
                'name' => __('Linen Ledger'),
                'description' => __('Warm ivory workspace with deep ink text and a restrained cobalt accent.'),
                'light_values' => [
                    'accent_color' => '#1d4ed8',
                    'sidebar_bg_color' => '#ffffff',
                    'header_bg_color' => '#fffdf8',
                    'header_active_color' => '#0f172a',
                    'link_color' => '#1d4ed8',
                    'link_hover_color' => '#1e40af',
                    'border_color' => '#efefef',
                    'muted_text_color' => '#6b7280',
                    'sidebar_text_color' => '#44403c',
                    'header_text_color' => '#1c1917',
                    'success_color' => '#15803d',
                    'warning_color' => '#c2410c',
                    'danger_color' => '#b91c1c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#93c5fd',
                    'dark_sidebar_bg_color' => '#111827',
                    'dark_header_bg_color' => '#172033',
                    'dark_header_active_color' => '#f8fafc',
                    'dark_link_color' => '#93c5fd',
                    'dark_link_hover_color' => '#bfdbfe',
                    'dark_border_color' => '#334155',
                    'dark_muted_text_color' => '#94a3b8',
                    'dark_sidebar_text_color' => '#cbd5e1',
                    'dark_header_text_color' => '#f8fafc',
                    'dark_success_color' => '#34d399',
                    'dark_warning_color' => '#fb923c',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'atlas-blueprint',
                'name' => __('Atlas Blueprint'),
                'description' => __('Clean steel-blue admin shell with stronger navigation contrast and clearer structure.'),
                'light_values' => [
                    'accent_color' => '#1d4ed8',
                    'sidebar_bg_color' => '#f9fafb',
                    'header_bg_color' => '#ffffff',
                    'header_active_color' => '#1e3a8a',
                    'link_color' => '#1d4ed8',
                    'link_hover_color' => '#1e40af',
                    'border_color' => '#eceff3',
                    'muted_text_color' => '#64748b',
                    'sidebar_text_color' => '#334155',
                    'header_text_color' => '#0f172a',
                    'success_color' => '#0f766e',
                    'warning_color' => '#d97706',
                    'danger_color' => '#be123c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#93c5fd',
                    'dark_sidebar_bg_color' => '#0f172a',
                    'dark_header_bg_color' => '#111827',
                    'dark_header_active_color' => '#dbeafe',
                    'dark_link_color' => '#93c5fd',
                    'dark_link_hover_color' => '#bfdbfe',
                    'dark_border_color' => '#3b475b',
                    'dark_muted_text_color' => '#94a3b8',
                    'dark_sidebar_text_color' => '#cbd5e1',
                    'dark_header_text_color' => '#f8fafc',
                    'dark_success_color' => '#2dd4bf',
                    'dark_warning_color' => '#f59e0b',
                    'dark_danger_color' => '#fb7185',
                ],
            ],
            [
                'key' => 'grove-office',
                'name' => __('Grove Office'),
                'description' => __('Muted sage surfaces with dark forest text for calmer, more premium dashboards.'),
                'light_values' => [
                    'accent_color' => '#0f766e',
                    'sidebar_bg_color' => '#fcfcfd',
                    'header_bg_color' => '#fbfcfa',
                    'header_active_color' => '#14532d',
                    'link_color' => '#0f766e',
                    'link_hover_color' => '#115e59',
                    'border_color' => '#edf1ed',
                    'muted_text_color' => '#667564',
                    'sidebar_text_color' => '#3f4b3c',
                    'header_text_color' => '#1f2937',
                    'success_color' => '#166534',
                    'warning_color' => '#ca8a04',
                    'danger_color' => '#b91c1c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#5eead4',
                    'dark_sidebar_bg_color' => '#10231c',
                    'dark_header_bg_color' => '#13221d',
                    'dark_header_active_color' => '#dcfce7',
                    'dark_link_color' => '#5eead4',
                    'dark_link_hover_color' => '#99f6e4',
                    'dark_border_color' => '#35504a',
                    'dark_muted_text_color' => '#9fb4aa',
                    'dark_sidebar_text_color' => '#d1fae5',
                    'dark_header_text_color' => '#f0fdf4',
                    'dark_success_color' => '#4ade80',
                    'dark_warning_color' => '#facc15',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'violet-boardroom',
                'name' => __('Violet Boardroom'),
                'description' => __('Clear violet accents on soft neutrals without the washed-out pastel look.'),
                'light_values' => [
                    'accent_color' => '#7c3aed',
                    'sidebar_bg_color' => '#f5f5f5',
                    'header_bg_color' => '#fcfbff',
                    'header_active_color' => '#581c87',
                    'link_color' => '#7c3aed',
                    'link_hover_color' => '#6d28d9',
                    'border_color' => '#f0edfa',
                    'muted_text_color' => '#6b7280',
                    'sidebar_text_color' => '#4c1d95',
                    'header_text_color' => '#3b0764',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#be123c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#c4b5fd',
                    'dark_sidebar_bg_color' => '#1e1b4b',
                    'dark_header_bg_color' => '#22163f',
                    'dark_header_active_color' => '#ede9fe',
                    'dark_link_color' => '#c4b5fd',
                    'dark_link_hover_color' => '#ddd6fe',
                    'dark_border_color' => '#4c3f73',
                    'dark_muted_text_color' => '#b4a7d6',
                    'dark_sidebar_text_color' => '#ddd6fe',
                    'dark_header_text_color' => '#f5f3ff',
                    'dark_success_color' => '#34d399',
                    'dark_warning_color' => '#f59e0b',
                    'dark_danger_color' => '#fb7185',
                ],
            ],
            [
                'key' => 'terracotta-desk',
                'name' => __('Terracotta Desk'),
                'description' => __('Burnt orange and parchment tones for finance, operations, and document-heavy admin work.'),
                'light_values' => [
                    'accent_color' => '#c2410c',
                    'sidebar_bg_color' => '#f3f4f6',
                    'header_bg_color' => '#fffaf5',
                    'header_active_color' => '#7c2d12',
                    'link_color' => '#c2410c',
                    'link_hover_color' => '#9a3412',
                    'border_color' => '#f4eeea',
                    'muted_text_color' => '#8a6f61',
                    'sidebar_text_color' => '#7c2d12',
                    'header_text_color' => '#431407',
                    'success_color' => '#0f766e',
                    'warning_color' => '#ea580c',
                    'danger_color' => '#be123c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#fdba74',
                    'dark_sidebar_bg_color' => '#2b1711',
                    'dark_header_bg_color' => '#2c1913',
                    'dark_header_active_color' => '#ffedd5',
                    'dark_link_color' => '#fdba74',
                    'dark_link_hover_color' => '#fed7aa',
                    'dark_border_color' => '#5b4638',
                    'dark_muted_text_color' => '#c2a89a',
                    'dark_sidebar_text_color' => '#fed7aa',
                    'dark_header_text_color' => '#fff7ed',
                    'dark_success_color' => '#2dd4bf',
                    'dark_warning_color' => '#fb923c',
                    'dark_danger_color' => '#fb7185',
                ],
            ],
            [
                'key' => 'graphite-signal',
                'name' => __('Graphite Signal'),
                'description' => __('Neutral graphite shell with crisp monochrome text and stronger action contrast.'),
                'light_values' => [
                    'accent_color' => '#111827',
                    'sidebar_bg_color' => '#eef1f4',
                    'header_bg_color' => '#ffffff',
                    'header_active_color' => '#111827',
                    'link_color' => '#111827',
                    'link_hover_color' => '#020617',
                    'border_color' => '#e9edf1',
                    'muted_text_color' => '#6b7280',
                    'sidebar_text_color' => '#374151',
                    'header_text_color' => '#111827',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#e5e7eb',
                    'dark_sidebar_bg_color' => '#111827',
                    'dark_header_bg_color' => '#0f172a',
                    'dark_header_active_color' => '#f9fafb',
                    'dark_link_color' => '#e5e7eb',
                    'dark_link_hover_color' => '#f8fafc',
                    'dark_border_color' => '#3f3f46',
                    'dark_muted_text_color' => '#9ca3af',
                    'dark_sidebar_text_color' => '#d1d5db',
                    'dark_header_text_color' => '#f9fafb',
                    'dark_success_color' => '#34d399',
                    'dark_warning_color' => '#f59e0b',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'ocean-command',
                'name' => __('Ocean Command'),
                'description' => __('Marine blues and cyan accents for data-heavy dashboards that need a colder, technical feel.'),
                'light_values' => [
                    'accent_color' => '#0284c7',
                    'sidebar_bg_color' => '#eef8ff',
                    'header_bg_color' => '#f8fdff',
                    'header_active_color' => '#0f3b57',
                    'link_color' => '#0284c7',
                    'link_hover_color' => '#0369a1',
                    'border_color' => '#dbeafe',
                    'muted_text_color' => '#5b7285',
                    'sidebar_text_color' => '#24506a',
                    'header_text_color' => '#0f172a',
                    'success_color' => '#0f766e',
                    'warning_color' => '#d97706',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#38bdf8',
                    'dark_sidebar_bg_color' => '#082032',
                    'dark_header_bg_color' => '#0b2740',
                    'dark_header_active_color' => '#e0f2fe',
                    'dark_link_color' => '#38bdf8',
                    'dark_link_hover_color' => '#7dd3fc',
                    'dark_border_color' => '#1f4c67',
                    'dark_muted_text_color' => '#8aa9bd',
                    'dark_sidebar_text_color' => '#c7e7f7',
                    'dark_header_text_color' => '#f0f9ff',
                    'dark_success_color' => '#2dd4bf',
                    'dark_warning_color' => '#f59e0b',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'ruby-ledger',
                'name' => __('Ruby Ledger'),
                'description' => __('Warm red and rose tones tuned for approvals, finance ops, and alert-driven admin workflows.'),
                'light_values' => [
                    'accent_color' => '#be123c',
                    'sidebar_bg_color' => '#fff5f7',
                    'header_bg_color' => '#fffafb',
                    'header_active_color' => '#881337',
                    'link_color' => '#be123c',
                    'link_hover_color' => '#9f1239',
                    'border_color' => '#f3d7df',
                    'muted_text_color' => '#8b5f6b',
                    'sidebar_text_color' => '#7f1d3f',
                    'header_text_color' => '#4c0519',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#be123c',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#fb7185',
                    'dark_sidebar_bg_color' => '#34111f',
                    'dark_header_bg_color' => '#431323',
                    'dark_header_active_color' => '#ffe4e6',
                    'dark_link_color' => '#fda4af',
                    'dark_link_hover_color' => '#fecdd3',
                    'dark_border_color' => '#6b2138',
                    'dark_muted_text_color' => '#c08d9b',
                    'dark_sidebar_text_color' => '#ffd4dc',
                    'dark_header_text_color' => '#fff1f2',
                    'dark_success_color' => '#4ade80',
                    'dark_warning_color' => '#fb923c',
                    'dark_danger_color' => '#fb7185',
                ],
            ],
            [
                'key' => 'amber-terminal',
                'name' => __('Amber Terminal'),
                'description' => __('Honeyed neutrals with darker amber contrast for operational consoles and monitoring views.'),
                'light_values' => [
                    'accent_color' => '#d97706',
                    'sidebar_bg_color' => '#fff9ed',
                    'header_bg_color' => '#fffdf6',
                    'header_active_color' => '#92400e',
                    'link_color' => '#d97706',
                    'link_hover_color' => '#b45309',
                    'border_color' => '#f3e2b8',
                    'muted_text_color' => '#8a7248',
                    'sidebar_text_color' => '#8b5e1a',
                    'header_text_color' => '#422006',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#fbbf24',
                    'dark_sidebar_bg_color' => '#261a08',
                    'dark_header_bg_color' => '#302008',
                    'dark_header_active_color' => '#fef3c7',
                    'dark_link_color' => '#fbbf24',
                    'dark_link_hover_color' => '#fde68a',
                    'dark_border_color' => '#5e4520',
                    'dark_muted_text_color' => '#c4a971',
                    'dark_sidebar_text_color' => '#f7dfab',
                    'dark_header_text_color' => '#fffbeb',
                    'dark_success_color' => '#4ade80',
                    'dark_warning_color' => '#fbbf24',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'mint-circuit',
                'name' => __('Mint Circuit'),
                'description' => __('Fresh mint and graphite pairing for product teams that want a lighter, startup-style admin tone.'),
                'light_values' => [
                    'accent_color' => '#10b981',
                    'sidebar_bg_color' => '#f1fcf8',
                    'header_bg_color' => '#fbfffd',
                    'header_active_color' => '#065f46',
                    'link_color' => '#059669',
                    'link_hover_color' => '#047857',
                    'border_color' => '#d8f0e5',
                    'muted_text_color' => '#5f7d72',
                    'sidebar_text_color' => '#166534',
                    'header_text_color' => '#064e3b',
                    'success_color' => '#059669',
                    'warning_color' => '#ca8a04',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#34d399',
                    'dark_sidebar_bg_color' => '#0b2019',
                    'dark_header_bg_color' => '#102720',
                    'dark_header_active_color' => '#d1fae5',
                    'dark_link_color' => '#6ee7b7',
                    'dark_link_hover_color' => '#a7f3d0',
                    'dark_border_color' => '#275244',
                    'dark_muted_text_color' => '#92b5a8',
                    'dark_sidebar_text_color' => '#d7f6ea',
                    'dark_header_text_color' => '#ecfdf5',
                    'dark_success_color' => '#34d399',
                    'dark_warning_color' => '#facc15',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'slate-radar',
                'name' => __('Slate Radar'),
                'description' => __('Soft slate surfaces with blue-grey contrast for enterprise dashboards that need calm, durable visuals.'),
                'light_values' => [
                    'accent_color' => '#475569',
                    'sidebar_bg_color' => '#f4f7fb',
                    'header_bg_color' => '#fbfdff',
                    'header_active_color' => '#1e293b',
                    'link_color' => '#334155',
                    'link_hover_color' => '#1e293b',
                    'border_color' => '#d9e1ea',
                    'muted_text_color' => '#718096',
                    'sidebar_text_color' => '#475569',
                    'header_text_color' => '#0f172a',
                    'success_color' => '#0f766e',
                    'warning_color' => '#d97706',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#94a3b8',
                    'dark_sidebar_bg_color' => '#151c26',
                    'dark_header_bg_color' => '#1a2430',
                    'dark_header_active_color' => '#e2e8f0',
                    'dark_link_color' => '#cbd5e1',
                    'dark_link_hover_color' => '#f8fafc',
                    'dark_border_color' => '#334155',
                    'dark_muted_text_color' => '#94a3b8',
                    'dark_sidebar_text_color' => '#dbe5f0',
                    'dark_header_text_color' => '#f8fafc',
                    'dark_success_color' => '#2dd4bf',
                    'dark_warning_color' => '#f59e0b',
                    'dark_danger_color' => '#f87171',
                ],
            ],
            [
                'key' => 'orchid-studio',
                'name' => __('Orchid Studio'),
                'description' => __('Magenta-orchid accents over neutral shells for creative tools, media workflows, and content teams.'),
                'light_values' => [
                    'accent_color' => '#c026d3',
                    'sidebar_bg_color' => '#fdf5ff',
                    'header_bg_color' => '#fffaff',
                    'header_active_color' => '#86198f',
                    'link_color' => '#c026d3',
                    'link_hover_color' => '#a21caf',
                    'border_color' => '#f1d6f7',
                    'muted_text_color' => '#8a6992',
                    'sidebar_text_color' => '#8b2f92',
                    'header_text_color' => '#581c87',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#e11d48',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#e879f9',
                    'dark_sidebar_bg_color' => '#291334',
                    'dark_header_bg_color' => '#341542',
                    'dark_header_active_color' => '#fae8ff',
                    'dark_link_color' => '#f0abfc',
                    'dark_link_hover_color' => '#f5d0fe',
                    'dark_border_color' => '#5f2f70',
                    'dark_muted_text_color' => '#be9ac7',
                    'dark_sidebar_text_color' => '#f5dbfb',
                    'dark_header_text_color' => '#fdf4ff',
                    'dark_success_color' => '#4ade80',
                    'dark_warning_color' => '#fb923c',
                    'dark_danger_color' => '#fb7185',
                ],
            ],
            [
                'key' => 'arctic-ops',
                'name' => __('Arctic Ops'),
                'description' => __('Very light icy surfaces and deep navy text for support centers, CRM panels, and workflow-heavy admin screens.'),
                'light_values' => [
                    'accent_color' => '#2563eb',
                    'sidebar_bg_color' => '#f3f9ff',
                    'header_bg_color' => '#fcfeff',
                    'header_active_color' => '#1e40af',
                    'link_color' => '#2563eb',
                    'link_hover_color' => '#1d4ed8',
                    'border_color' => '#dbe7f3',
                    'muted_text_color' => '#64748b',
                    'sidebar_text_color' => '#36506d',
                    'header_text_color' => '#0f172a',
                    'success_color' => '#059669',
                    'warning_color' => '#d97706',
                    'danger_color' => '#dc2626',
                ],
                'dark_values' => [
                    'dark_accent_color' => '#60a5fa',
                    'dark_sidebar_bg_color' => '#0c1726',
                    'dark_header_bg_color' => '#122033',
                    'dark_header_active_color' => '#dbeafe',
                    'dark_link_color' => '#93c5fd',
                    'dark_link_hover_color' => '#bfdbfe',
                    'dark_border_color' => '#29415f',
                    'dark_muted_text_color' => '#97abc2',
                    'dark_sidebar_text_color' => '#dbeafe',
                    'dark_header_text_color' => '#eff6ff',
                    'dark_success_color' => '#34d399',
                    'dark_warning_color' => '#fbbf24',
                    'dark_danger_color' => '#f87171',
                ],
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-6">
        <div class="space-y-8">
            <x-ui.page-hero
                :eyebrow="__('Theme registry')"
                :title="__('Backend Themes')"
                :description="__('Manage the administrative shell, navigation model, and runtime design tokens that shape the app workspace.')"
                :count="$themeSummary['library']"
                icon="fa-light fa-monitor-waveform"
            >
                <x-slot:actions>
                    <x-ui.button href="{{ route('admin-themes.frontend') }}" variant="outline" wire:navigate>{{ __('Open frontend themes') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.page-hero>

            <x-ui.metric-strip
                :items="[
                    ['label' => __('Library'), 'value' => number_format($themeSummary['library']), 'description' => __('Installed backend theme options ready for selection.'), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
                    ['label' => __('Editor fields'), 'value' => number_format($themeSummary['editor_fields']), 'description' => __('Theme settings currently exposed in the active schema.'), 'progress' => $themeSummary['editor_fields'] > 0 ? 100 : 0, 'tone' => 'var(--theme-warning-color)'],
                    ['label' => __('Navigation'), 'value' => number_format($themeSummary['navigation_modes']), 'description' => __('Navigation modes supported by the selected backend theme.'), 'progress' => $themeSummary['navigation_modes'] > 0 ? 100 : 0, 'tone' => 'var(--theme-success-color)'],
                    ['label' => __('Guest themes'), 'value' => number_format($themeSummary['guest_themes']), 'description' => __('Public theme variants available alongside the admin shell.'), 'progress' => $themeSummary['guest_themes'] > 0 ? 100 : 0, 'tone' => 'var(--theme-muted-text-color)'],
                ]"
                :show-icons="false"
                columns="md:grid-cols-2 xl:grid-cols-4"
            />

        <form
            id="backend-theme-form"
            method="POST"
            action="{{ route('admin-themes.backend.update') }}"
            x-on:submit.prevent="submitThemeForm($el, $event)"
            class="space-y-8"
            x-data="{
                themeTab: @js(request('tab', 'select-theme')),
                themeName: @js(old('backend_theme', $backendTheme)),
                submitIntent: '',
                preview: @js($backendPreviewState),
                fontStacks: @js($fontStacks),
                libraryTagFilter: 'all',
                libraryDarkFilter: 'all',
                importModalOpen: @js($errors->has('backend_import_json')),
                themeSupports(theme, feature) {
                    const supports = Array.isArray(theme?.supports) ? theme.supports.map((item) => String(item)) : [];

                    return supports.includes(feature);
                },
                matchesLibraryTheme(theme) {
                    const tags = Array.isArray(theme?.tags) ? theme.tags.map((item) => String(item)) : [];

                    if (this.libraryTagFilter !== 'all' && !tags.includes(this.libraryTagFilter)) {
                        return false;
                    }

                    if (this.libraryDarkFilter === 'dark' && !this.themeSupports(theme, 'dark-mode')) {
                        return false;
                    }

                    return true;
                },
                applyPalette(lightValues, darkValues = {}) {
                    Object.entries({ ...lightValues, ...darkValues }).forEach(([key, value]) => {
                        this.preview[key] = value;
                    });
                },
                paletteMatches(lightValues, darkValues = {}) {
                    return Object.entries({ ...lightValues, ...darkValues }).every(([key, value]) => String(this.preview[key] ?? '').toLowerCase() === String(value ?? '').toLowerCase());
                },
                previewFont() {
                    return this.fontStacks[this.preview.font_family] || this.fontStacks.inter;
                },
                submitThemeForm(form, event) {
                    try {
                        const formData = new FormData(form);
                        formData.set('intent', this.submitIntent || '');
                        const params = new URLSearchParams();
                        for (const [key, value] of formData.entries()) {
                            params.append(key, String(value));
                        }
                        $wire.submit(params.toString());
                    } catch (error) {
                        console.error('Livewire submit failed, fallback to standard submit.', error);
                        form.removeAttribute('x-on:submit.prevent');
                        form.requestSubmit(event?.submitter ?? undefined);
                    }
                },
            }"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" x-model="themeTab">
            <input type="hidden" name="backend_theme" x-model="themeName">
            <input type="hidden" name="intent" x-model="submitIntent">

            <div class="grid gap-8 xl:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="space-y-3 xl:sticky xl:top-24 xl:self-start">
                    <x-theme.sidebar-tabs
                        :library-count="count($appThemes)"
                        :customize-count="count($backendThemeSchema)"
                        :library-description="__('Browse installed backend themes')"
                        :customize-description="__('Adjust shell colors and layout')"
                    />

                    @if (! empty($backendThemeSchema))
                        <div x-show="themeTab === 'theme-settings'" x-cloak>
                            <x-theme.section-card :title="__('Transfer settings')" body-class="space-y-5 p-5">
                                <div class="space-y-2">
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Export the current backend theme as JSON, or open the import modal to restore colors, typography, and navigation settings.') }}</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-ui.button :href="route('admin-themes.backend.export')" variant="outline" class="w-full justify-center whitespace-nowrap">
                                            {{ __('Export JSON') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" variant="secondary" x-on:click="importModalOpen = true" class="w-full justify-center whitespace-nowrap">
                                            {{ __('Import JSON') }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </x-theme.section-card>
                        </div>
                    @endif
                </aside>

                <div class="space-y-8">
                    <div x-show="themeTab === 'select-theme'" x-cloak>
                        <x-theme.section-card
                            :title="__('App area')"
                            :description="__('Choose the administrative shell used for the dashboard, modules, and management interfaces.')"
                            body-class="space-y-5 p-6"
                        >
                            <x-slot:meta>
                                <span class="hidden rounded-full border border-slate-200/85 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 md:inline-flex dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                                    {{ count($appThemes) }} {{ __('themes') }}
                                </span>
                            </x-slot:meta>

                            <div class="flex flex-col gap-4 rounded-[1.1rem] border border-slate-200/80 bg-[linear-gradient(180deg,#fbfdff_0%,#f7faff_100%)] p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.12)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#111827_100%)] lg:flex-row lg:items-end lg:justify-between">
                                <div class="space-y-1.5">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500">
                                        <i class="fa-light fa-sliders text-[10px]"></i>
                                        {{ __('Library filters') }}
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Find the right shell faster') }}</p>
                                    <p class="max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Narrow the library by use case tags or show only themes that explicitly support dark mode.') }}</p>
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <label class="block">
                                        <span class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Tag') }}</span>
                                        <div class="relative">
                                            <select x-model="libraryTagFilter" class="h-11 min-w-[220px] appearance-none rounded-[0.85rem] border border-slate-200 bg-white px-4 pr-12 text-sm font-medium text-slate-700 outline-none shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                <option value="all">{{ __('All tags') }}</option>
                                                @foreach ($backendLibraryTags as $tag)
                                                    <option value="{{ $tag }}">{{ str($tag)->replace('-', ' ')->headline() }}</option>
                                                @endforeach
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400"><i class="fa-light fa-chevron-down text-xs"></i></span>
                                        </div>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Support') }}</span>
                                        <div class="relative">
                                            <select x-model="libraryDarkFilter" class="h-11 min-w-[220px] appearance-none rounded-[0.85rem] border border-slate-200 bg-white px-4 pr-12 text-sm font-medium text-slate-700 outline-none shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                <option value="all">{{ __('All themes') }}</option>
                                                <option value="dark">{{ __('Supports dark mode') }}</option>
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400"><i class="fa-light fa-chevron-down text-xs"></i></span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($appThemes as $theme)
                                    @php($previewImage = $theme->meta['thumbnail'] ?? $theme->meta['preview'] ?? null)
                                    @php($supports = is_array($theme->meta['supports'] ?? null) ? $theme->meta['supports'] : [])
                                    @php($tags = is_array($theme->meta['tags'] ?? null) ? $theme->meta['tags'] : [])
                                    @php($recommended = is_array($theme->meta['recommended_for'] ?? null) ? $theme->meta['recommended_for'] : [])
                                    @php($themeDefaults = $appThemeDefaults[$theme->name] ?? [])
                                    @php($themeDefaultNavigation = $appThemeDefaultNavigation[$theme->name] ?? 'sidebar')
                                    <label class="block cursor-pointer" x-show="matchesLibraryTheme(@js($theme->meta))">
                                        <input type="radio" name="backend_theme" value="{{ $theme->name }}" class="sr-only peer" @checked(old('backend_theme', $backendTheme) === $theme->name) x-on:change="themeName = @js($theme->name)">
                                        <span class="relative flex h-full flex-col overflow-visible rounded-[1.15rem] border border-slate-200/85 bg-[linear-gradient(180deg,#ffffff_0%,#fbfdff_100%)] shadow-[0_24px_48px_-38px_rgba(15,23,42,0.16)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_30px_54px_-40px_rgba(15,23,42,0.22)] peer-checked:border-[var(--theme-accent,#4f46e5)] peer-checked:shadow-[0_28px_58px_-40px_rgba(79,70,229,0.24)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#111827_100%)] dark:peer-checked:border-[var(--theme-accent,#818cf8)]">
                                            <span class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--theme-accent,#4f46e5)]/35 to-transparent opacity-0 transition peer-checked:opacity-100"></span>
                                            <span class="flex items-start justify-between gap-3 px-5 pb-4 pt-5">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ $theme->meta['name'] ?? ucfirst($theme->name) }}</span>
                                                    <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">{{ $theme->name }}</span>
                                                </span>
                                                <span class="inline-flex items-center rounded-full border border-slate-200/85 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">{{ __('App') }}</span>
                                            </span>
                                            <div class="space-y-4 p-5">
                                                <div class="overflow-hidden rounded-[1rem] border border-slate-200/80 bg-[linear-gradient(180deg,#f8fbff_0%,#f3f7ff_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.85)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#111827_0%,#0f172a_100%)]">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="px-4 pt-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">{{ __('Preview') }}</span>
                                                        <div class="mr-4 mt-4 flex items-center gap-2">
                                                            @if (in_array('dark-mode', $supports, true))
                                                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">{{ __('Dark mode') }}</span>
                                                            @endif
                                                            <span class="inline-flex size-7 items-center justify-center rounded-full border border-slate-200/85 bg-white text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"><i class="fa-light fa-window-frame-open"></i></span>
                                                        </div>
                                                    </div>
                                                    @if ($previewImage)
                                                        <div class="px-4 pb-4 pt-3">
                                                            <div class="overflow-hidden rounded-[0.8rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950">
                                                                <img src="{{ theme_asset_for($theme, $previewImage) }}" alt="{{ $theme->meta['name'] ?? $theme->name }}" class="block h-44 w-full object-cover object-top">
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="px-4 pb-4 pt-3">
                                                            <div class="space-y-2.5">
                                                                <div class="h-2.5 rounded-full bg-slate-200/95 dark:bg-slate-800"></div>
                                                                <div class="h-2.5 w-5/6 rounded-full bg-slate-200/80 dark:bg-slate-800/80"></div>
                                                                <div class="grid grid-cols-3 gap-2 pt-2">
                                                                    <div class="h-12 rounded-[0.75rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950"></div>
                                                                    <div class="h-12 rounded-[0.75rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950"></div>
                                                                    <div class="h-12 rounded-[0.75rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="space-y-3">
                                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $theme->meta['description'] ?? __('No description provided.') }}</p>
                                                    <div class="grid gap-2">
                                                        <div class="flex items-center justify-between gap-3 rounded-[0.9rem] border border-slate-200/80 bg-slate-50/70 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                                                            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Author') }}</span>
                                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $theme->meta['author'] ?? __('Unknown') }}</span>
                                                        </div>
                                                    </div>
                                                    @if ($tags)
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach ($tags as $tag)
                                                                <span class="inline-flex items-center rounded-full border border-slate-200/80 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">{{ str($tag)->replace('-', ' ')->headline() }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mt-auto flex items-center border-t border-slate-200/80 bg-slate-50/70 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40">
                                                <span class="inline-flex items-center gap-2 text-sm font-medium {{ old('backend_theme', $backendTheme) === $theme->name ? 'text-slate-950 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">
                                                    <span class="inline-flex size-2 rounded-full {{ old('backend_theme', $backendTheme) === $theme->name ? 'bg-[var(--theme-accent,#4f46e5)] shadow-[0_0_0_4px_rgba(var(--theme-accent-rgb),0.12)]' : 'bg-slate-300 dark:bg-slate-700' }}"></span>
                                                    {{ old('backend_theme', $backendTheme) === $theme->name ? __('Selected') : __('Available') }}
                                                </span>
                                            </div>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </x-theme.section-card>
                    </div>

                    @if (! empty($backendThemeSchema))
                        <div x-show="themeTab === 'theme-settings'" x-cloak class="space-y-6 pb-28">
                            <div id="app-theme-settings" class="space-y-6">
                                <div class="space-y-1">
                                    <h2 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('App theme customization') }}</h2>
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Tune the administrative theme colors, layout behavior, and base typography for the current backend shell.') }}</p>
                                </div>

                                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)]">
                                    <div class="space-y-6">
                                        @if (collect([...$backendLightColorKeys, ...$backendDarkColorKeys])->contains(fn ($key) => isset($backendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Color system')" body-class="space-y-5 p-5">
                                                <div class="rounded-[0.9rem] border border-dashed border-slate-300/90 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div>
                                                            <h4 class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Preset palettes') }}</h4>
                                                            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Apply a full backend color set in one click, then adjust individual swatches only if needed.') }}</p>
                                                        </div>
                                                        <span class="inline-flex shrink-0 whitespace-nowrap rounded-full border border-slate-200/80 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500">{{ __('Quick apply') }}</span>
                                                    </div>

                                                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                                        @foreach ($backendPalettePresets as $palette)
                                                            <button
                                                                type="button"
                                                                x-on:click="applyPalette(@js($palette['light_values']), @js($palette['dark_values']))"
                                                                class="group rounded-[0.95rem] border border-slate-200/85 bg-white p-4 text-left shadow-[0_12px_34px_-28px_rgba(15,23,42,0.14)] transition hover:border-slate-300 hover:shadow-[0_18px_40px_-32px_rgba(15,23,42,0.18)] dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-slate-700"
                                                                x-bind:class="paletteMatches(@js($palette['light_values']), @js($palette['dark_values'])) ? 'border-[var(--theme-accent)] bg-[color:rgba(var(--theme-accent-rgb),0.08)] shadow-[0_18px_42px_-30px_rgba(var(--theme-accent-rgb),0.35)] dark:border-[var(--theme-accent)]' : ''"
                                                            >
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $palette['name'] }}</p>
                                                                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $palette['description'] }}</p>
                                                                    </div>
                                                                    <span class="rounded-full border border-slate-200/80 bg-slate-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500" x-text="paletteMatches(@js($palette['light_values']), @js($palette['dark_values'])) ? @js(__('Active')) : @js(__('Palette'))"></span>
                                                                </div>

                                                                <div class="mt-4 flex flex-wrap gap-2">
                                                                    @foreach (array_slice(array_values($palette['light_values']), 0, 6) as $swatch)
                                                                        <span class="size-7 rounded-full border border-white shadow-sm ring-1 ring-black/5 dark:border-slate-900" style="background-color: {{ $swatch }}"></span>
                                                                    @endforeach
                                                                    @foreach (array_slice(array_values($palette['dark_values']), 0, 6) as $swatch)
                                                                        <span class="size-7 rounded-full border border-white shadow-sm ring-1 ring-black/5 dark:border-slate-900" style="background-color: {{ $swatch }}"></span>
                                                                    @endforeach
                                                                </div>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div class="space-y-4">
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Light palette') }}</h4>
                                                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Colors used when the backend runs in light appearance.') }}</p>
                                                    </div>
                                                    <div class="grid gap-5 md:grid-cols-2">
                                                    @foreach ($backendLightColorKeys as $key)
                                                        @continue(! isset($backendThemeSchema[$key]))
                                                        @php($field = $backendThemeSchema[$key])
                                                        <x-theme.swatch-editor
                                                            :input-name="'backend_settings[' . $key . ']'"
                                                            :key-name="$key"
                                                            :label="__($field['label'] ?? str($key)->headline())"
                                                            :value="old('backend_settings.' . $key, $backendThemeValues[$key] ?? ($field['default'] ?? '#ffffff'))"
                                                            :presets="$backendColorPresets[$key] ?? []"
                                                            preview-state="preview"
                                                            :picker-ref="'backendColor' . $loop->index"
                                                        />
                                                    @endforeach
                                                    </div>
                                                </div>

                                                <div class="space-y-4 border-t border-slate-200 pt-5 dark:border-slate-800">
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Dark palette') }}</h4>
                                                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Colors used when the backend runs in dark appearance.') }}</p>
                                                    </div>
                                                    <div class="grid gap-5 md:grid-cols-2">
                                                    @foreach ($backendDarkColorKeys as $key)
                                                        @continue(! isset($backendThemeSchema[$key]))
                                                        @php($field = $backendThemeSchema[$key])
                                                        <x-theme.swatch-editor
                                                            :input-name="'backend_settings[' . $key . ']'"
                                                            :key-name="$key"
                                                            :label="__($field['label'] ?? str($key)->headline())"
                                                            :value="old('backend_settings.' . $key, $backendThemeValues[$key] ?? ($field['default'] ?? '#ffffff'))"
                                                            :presets="$backendColorPresets[$key] ?? []"
                                                            preview-state="preview"
                                                            :picker-ref="'backendDarkColor' . $loop->index"
                                                        />
                                                    @endforeach
                                                    </div>
                                                </div>
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($backendTypographyKeys)->contains(fn ($key) => isset($backendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Typography')" body-class="space-y-5 p-5">
                                                @foreach ($backendTypographyKeys as $key)
                                                    @continue(! isset($backendThemeSchema[$key]))
                                                    @php($field = $backendThemeSchema[$key])
                                                    <label class="block">
                                                        <span class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __($field['label'] ?? str($key)->headline()) }}</span>
                                                        <div class="relative">
                                                            <select name="backend_settings[{{ $key }}]" class="h-11 w-full appearance-none rounded-[0.65rem] border border-slate-200 bg-white px-4 pr-14 text-sm font-medium text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                                @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                                                    <option value="{{ $optionValue }}" @selected(old("backend_settings.$key", $backendThemeValues[$key] ?? ($field['default'] ?? null)) == $optionValue)>{{ __($optionLabel) }}</option>
                                                                @endforeach
                                                            </select>
                                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400">
                                                                <i class="fa-light fa-chevron-down text-xs"></i>
                                                            </span>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif

                                        <x-theme.section-card :title="__('Layout & appearance')" body-class="space-y-5 p-5">
                                            @foreach ($backendLayoutKeys as $key)
                                                @continue(! isset($backendThemeSchema[$key]))
                                                @php($field = $backendThemeSchema[$key])
                                                <x-ui.radio-group
                                                    :name="'backend_settings[' . $key . ']'"
                                                    :label="__($field['label'] ?? str($key)->headline())"
                                                    :value="old('backend_settings.' . $key, $backendThemeValues[$key] ?? ($field['default'] ?? null))"
                                                    :options="collect($field['options'] ?? [])->map(fn ($optionLabel, $optionValue) => [
                                                        'value' => $optionValue,
                                                        'label' => __($optionLabel),
                                                    ])->values()->all()"
                                                />
                                            @endforeach

                                            @if (count($backendNavigationModes) > 1)
                                                <div class="space-y-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Navigation mode') }}</p>
                                                    @foreach ($backendNavigationModes as $mode)
                                                        <label class="block cursor-pointer">
                                                            <input type="radio" name="backend_navigation" value="{{ $mode }}" class="sr-only peer" @checked(old('backend_navigation', $backendNavigation) === $mode)>
                                                            <span class="block rounded-[0.7rem] border border-slate-200 bg-white px-4 py-4 transition peer-checked:border-slate-950 peer-checked:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:peer-checked:border-slate-200 dark:peer-checked:bg-slate-900">
                                                                <span class="block text-sm font-semibold text-slate-950 dark:text-white">{{ str($mode)->headline() }}</span>
                                                                <span class="mt-2 block text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $mode === 'topbar' ? __('Show the main navigation as a horizontal header menu.') : __('Show the main navigation as a left sidebar.') }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </x-theme.section-card>

                                        @if (collect($backendComponentKeys)->contains(fn ($key) => isset($backendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Components')" body-class="space-y-5 p-5">
                                                @foreach ($backendComponentKeys as $key)
                                                    @continue(! isset($backendThemeSchema[$key]))
                                                    @php($field = $backendThemeSchema[$key])
                                                    @if (($field['type'] ?? null) === 'number')
                                                        @php($value = old("backend_settings.$key", $backendThemeValues[$key] ?? ($field['default'] ?? null)))
                                                        @php($numericValue = is_numeric($value)
                                                            ? $value
                                                            : (str_ends_with((string) $value, 'rem')
                                                                ? round((float) preg_replace('/[^\d.]/', '', (string) $value) * 16)
                                                                : preg_replace('/[^\d.]/', '', (string) $value)))
                                                        <div class="space-y-2.5">
                                                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __($field['label'] ?? str($key)->headline()) }}</span>
                                                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem]">
                                                                <div class="flex items-center rounded-[0.8rem] border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                                                                    <input
                                                                        type="range"
                                                                        min="{{ $field['min'] ?? 0 }}"
                                                                        max="{{ $field['max'] ?? 100 }}"
                                                                        step="{{ $field['step'] ?? 1 }}"
                                                                        value="{{ $numericValue }}"
                                                                        class="h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-[var(--theme-accent)] dark:bg-slate-700"
                                                                        oninput="this.closest('div').nextElementSibling.querySelector('input').value = this.value"
                                                                    >
                                                                </div>
                                                                <div class="flex items-center overflow-hidden rounded-[0.8rem] border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                                                    <input
                                                                        type="number"
                                                                        name="backend_settings[{{ $key }}]"
                                                                        min="{{ $field['min'] ?? 0 }}"
                                                                        max="{{ $field['max'] ?? 100 }}"
                                                                        step="{{ $field['step'] ?? 1 }}"
                                                                        value="{{ $numericValue }}"
                                                                        class="h-11 w-full border-0 bg-transparent px-3 text-sm font-medium text-slate-700 outline-none focus:ring-0 dark:text-slate-200"
                                                                        oninput="this.closest('.grid').querySelector('input[type=range]').value = this.value"
                                                                    >
                                                                    <span class="border-l border-slate-200 px-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:border-slate-700 dark:text-slate-500">{{ $field['unit'] ?? 'px' }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <x-ui.radio-group
                                                            :name="'backend_settings[' . $key . ']'"
                                                            :label="__($field['label'] ?? str($key)->headline())"
                                                            :value="old('backend_settings.' . $key, $backendThemeValues[$key] ?? ($field['default'] ?? null))"
                                                            :options="collect($field['options'] ?? [])->map(fn ($optionLabel, $optionValue) => [
                                                                'value' => $optionValue,
                                                                'label' => __($optionLabel),
                                                            ])->values()->all()"
                                                        />
                                                    @endif
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($backendAdvancedKeys)->contains(fn ($key) => isset($backendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Advanced')" body-class="space-y-5 p-5">
                                                @foreach ($backendAdvancedKeys as $key)
                                                    @continue(! isset($backendThemeSchema[$key]))
                                                    @php($field = $backendThemeSchema[$key])
                                                    <x-ui.code-editor
                                                        :name="'backend_settings[' . $key . ']'"
                                                        :label="__($field['label'] ?? str($key)->headline())"
                                                        :value="old('backend_settings.' . $key, $backendThemeValues[$key] ?? ($field['default'] ?? ''))"
                                                        :mode="str_contains($key, 'js') ? 'javascript' : 'css'"
                                                    />
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif
                                    </div>

                                    <div class="space-y-6">
                                        <div class="xl:sticky xl:top-24">
                                            <x-theme.preview-panel variant="backend" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <x-theme.sticky-save-bar
                        :title="__('Ready to publish changes?')"
                        :description="__('Save the active backend theme configuration.')"
                        :reset-label="__('Set default')"
                        :save-label="__('Save backend theme')"
                        form-id="backend-theme-form"
                        intent-model="submitIntent"
                    />
                </div>
            </div>

            <template x-teleport="body">
                <div x-cloak x-show="importModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-6" x-on:keydown.escape.window="importModalOpen = false">
                    <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" x-on:click="importModalOpen = false"></div>

                    <div x-show="importModalOpen" x-transition.opacity.scale.90 class="relative w-full max-w-2xl">
                        <div class="rounded-[var(--theme-card-radius,0.9rem)] border border-slate-200 bg-white shadow-[0_30px_80px_-30px_rgba(15,23,42,0.35)] dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Import backend theme JSON') }}</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Paste a JSON export to restore backend colors, typography, layout, and navigation settings.') }}</p>
                                </div>
                                <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="importModalOpen = false">
                                    <i class="fa-light fa-xmark text-lg"></i>
                                </button>
                            </div>

                            <div class="px-6 py-5">
                                <x-ui.textarea
                                    form="backend-theme-form"
                                    name="backend_import_json"
                                    :label="__('Import JSON')"
                                    rows="11"
                                    :error="$errors->first('backend_import_json')"
                                >{{ old('backend_import_json') }}</x-ui.textarea>
                            </div>

                            <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="importModalOpen = false">
                                        {{ __('Cancel') }}
                                    </x-ui.button>
                                    <x-ui.button type="submit" form="backend-theme-form" variant="secondary" name="intent" value="import_settings" x-on:click="submitIntent = 'import_settings'">
                                        {{ __('Import settings') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            </form>
        </div>

        <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.css') }}">
        <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/theme/material-darker.css') }}">
        <script src="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.js') }}"></script>
        <script src="{{ theme_shared_asset('plugins/codemirror5/mode/css/css.js') }}"></script>
        <script src="{{ theme_shared_asset('plugins/codemirror5/mode/javascript/javascript.js') }}"></script>
        <script>
            (() => {
                const initThemeCodeEditors = () => {
                    document.querySelectorAll('textarea[data-code-editor]').forEach((textarea) => {
                        if (textarea.dataset.editorReady === 'true') {
                            return;
                        }

                        textarea.dataset.editorReady = 'true';

                        const editor = CodeMirror.fromTextArea(textarea, {
                            mode: textarea.dataset.codeEditor,
                            theme: 'material-darker',
                            lineNumbers: true,
                            lineWrapping: true,
                            indentUnit: 4,
                            tabSize: 4,
                            indentWithTabs: false,
                            viewportMargin: Infinity,
                        });

                        editor.setSize(null, 280);
                        editor.on('change', (instance) => {
                            textarea.value = instance.getValue();
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initThemeCodeEditors, { once: true });
                } else {
                    initThemeCodeEditors();
                }
            })();
        </script>
    </div>
