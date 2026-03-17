<?php
if (!function_exists('getAssetCategories')) {
    /**
     * Return the shared asset category configuration.
     *
     * @return array
     */
    function getAssetCategories(): array
    {
        return [
            'all'           => [
                'label' => 'All assets',
                'icon'  => 'fa-layer-group',
                'types' => [],
                'color' => '#667eea',
            ],
            'computers'     => [
                'label' => 'Desktops & laptops',
                'icon'  => 'fa-desktop',
                'types' => ['desktop', 'laptop'],
                'color' => '#4299e1',
            ],
            'monitors'      => [
                'label' => 'Monitors & displays',
                'icon'  => 'fa-tv',
                'types' => ['monitor'],
                'color' => '#38a169',
            ],
            'network'       => [
                'label' => 'Network devices',
                'icon'  => 'fa-network-wired',
                'types' => ['network'],
                'color' => '#805ad5',
            ],
            'printers'      => [
                'label' => 'Print & scanning',
                'icon'  => 'fa-print',
                'types' => ['printer'],
                'color' => '#dd6b20',
            ],
            'phones'        => [
                'label' => 'Phones & mobile devices',
                'icon'  => 'fa-mobile-screen-button',
                'types' => ['mobile', 'phone'],
                'color' => '#e53e3e',
            ],
            'software'      => [
                'label' => 'Software & licenses',
                'icon'  => 'fa-floppy-disk',
                'types' => ['software'],
                'color' => '#3182ce',
            ],
            'infrastructure'=> [
                'label' => 'Infrastructure equipment',
                'icon'  => 'fa-server',
                'types' => ['rack', 'enclosure', 'pdu', 'passive_device'],
                'color' => '#2d3748',
            ],
            'connectivity'  => [
                'label' => 'Connectivity accessories',
                'icon'  => 'fa-plug',
                'types' => ['cable', 'simcard'],
                'color' => '#319795',
            ],
            'consumables'   => [
                'label' => 'Consumables & add-ons',
                'icon'  => 'fa-droplet',
                'types' => ['ink_cartridge', 'consumable', 'addon'],
                'color' => '#b7791f',
            ],
        ];
    }
}
