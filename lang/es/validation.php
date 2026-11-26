<?php

// Mensajes de validación del framework, en español (tuteo neutro), solo los de
// las reglas que usa Nexo ID; cualquier otra regla cae en el inglés del
// framework (fallback_locale). Antes, todos salían en inglés en la interfaz en
// español: «The password is incorrect.» al borrar la cuenta (H-nexoid-12).
return [
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'email' => 'Escribe un correo electrónico válido.',
    'max' => [
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'string' => ':Attribute tiene que tener al menos :min caracteres.',
    ],
    'required' => 'Completa :attribute.',
    'string' => ':Attribute tiene que ser un texto.',
    'unique' => 'Ya existe una cuenta con :attribute.',

    'attributes' => [
        'display_name' => 'el nombre visible',
        'email' => 'el correo electrónico',
        'password' => 'la contraseña',
        'current_password' => 'la contraseña actual',
        'delete_password' => 'tu contraseña',
    ],
];
