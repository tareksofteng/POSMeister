<?php

return [
    'serials' => [
        'duplicate' => [
            'title'   => 'Doppelte Seriennummer',
            'message' => 'Seriennummer „:sn" ist bereits registriert. Der Eintrag wurde abgewiesen.',
        ],
        'lowStock' => [
            'title'   => 'Niedriger Bestand — :name (noch :available)',
            'message' => 'Bestand für :name (:sku) in dieser Filiale beträgt :available Einheiten (Meldebestand: :threshold). Bitte Nachbestellung planen.',
        ],
        'damagedReturn' => [
            'title'   => 'Beschädigte Seriennummer zurückgegeben',
            'message' => 'Seriennummer :sn wurde vom Kunden zurückgegeben und als beschädigt markiert. Bitte Gerät prüfen und über Entsorgung oder Reparatur entscheiden.',
        ],
    ],
];
