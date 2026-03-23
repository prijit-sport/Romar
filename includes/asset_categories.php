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
'label' => 'ทรัพย์สินทั้งหมด',
                'icon'  => 'fa-layer-group',
                'types' => [],
                'color' => '#667eea',
            ],
            'computers'     => [
'label' => 'เดสทอป & โน๊ตบุ๊ค',
                'icon'  => 'fa-desktop',
                'types' => ['desktop', 'laptop'],
                'color' => '#4299e1',
            ],
            'monitors'      => [
'label' => 'มอนิเตอร์',
                'icon'  => 'fa-tv',
                'types' => ['monitor'],
                'color' => '#38a169',
            ],
            'network'       => [
'label' => 'เน็ตเวิค',
                'icon'  => 'fa-network-wired',
                'types' => ['network'],
                'color' => '#805ad5',
            ],
            'printers'      => [
'label' => 'เครื่องพิมพ์',
                'icon'  => 'fa-print',
                'types' => ['printer'],
                'color' => '#dd6b20',
            ],
            'phones'        => [
'label' => 'โทรศัพท์',
                'icon'  => 'fa-mobile-screen-button',
                'types' => ['mobile', 'phone'],
                'color' => '#e53e3e',
            ],
            'software'      => [
'label' => 'โปรแกรม',
                'icon'  => 'fa-floppy-disk',
                'types' => ['software'],
                'color' => '#3182ce',
            ],
            'infrastructure'=> [
'label' => 'โครงสร้างพื้นฐาน',
                'icon'  => 'fa-server',
                'types' => ['rack', 'enclosure', 'pdu', 'passive_device'],
                'color' => '#2d3748',
            ],
            'connectivity'  => [
'label' => 'อุปกรณ์เชื่อมต่อ',
                'icon'  => 'fa-plug',
                'types' => ['cable', 'simcard'],
                'color' => '#319795',
            ],
            'consumables'   => [
                'label' => 'วัสดุสิ้นเปลือง',
                'icon'  => 'fa-droplet',
                'types' => ['ink_cartridge', 'consumable', 'addon'],
                'color' => '#b7791f',
            ],
            'others'        => [
                'label' => 'อื่นๆ',
                'icon'  => 'fa-ellipsis-h',
                'types' => ['other', 'misc', 'accessory'],
                'color' => '#a0aec0',
            ],
        ];
    }
}
