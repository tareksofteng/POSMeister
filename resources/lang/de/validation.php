<?php

return [
    'required'  => 'Das Feld :attribute ist erforderlich.',
    'email'     => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'unique'    => 'Der Wert für :attribute ist bereits vergeben.',
    'min'       => [
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'max'       => [
        'string' => 'Das Feld :attribute darf nicht mehr als :max Zeichen haben.',
    ],
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',

    'attributes' => [
        'name'      => 'Name',
        'email'     => 'E-Mail',
        'password'  => 'Passwort',
        'phone'     => 'Telefon',
        'role'      => 'Rolle',
        'branch_id' => 'Filiale',
        'code'      => 'Code',
        'address'   => 'Adresse',
    ],
];
