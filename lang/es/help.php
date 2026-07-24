<?php

// Contenido del centro de ayuda de Nexo ID (help/index vía __('help.faqs')).
return [
    'meta_description' => 'Ayuda de Nexo ID: qué es el inicio de sesión único, para qué sirve tu cuenta, seguridad y privacidad, y cómo gestionar tus sesiones.',
    'faqs' => [
        [
            'q' => '¿Qué es Nexo ID?',
            'a' => 'Nexo ID es el inicio de sesión único del ecosistema Nexo: una sola cuenta para entrar a todas las herramientas Nexo, en lugar de una contraseña distinta por cada aplicación.',
        ],
        [
            'q' => '¿Para qué sirve?',
            'a' => 'Iniciás sesión una vez con Nexo ID y accedés a cualquier herramienta Nexo con la misma cuenta. Cuando una herramienta te pide iniciar sesión, la autorizás una vez en una pantalla de consentimiento y listo.',
        ],
        [
            'q' => '¿Es seguro? ¿Y mi privacidad?',
            'a' => 'Tu contraseña se guarda cifrada (hash), nunca en texto plano. Cada inicio de sesión es una sesión de dispositivo que podés revisar y cerrar. Una herramienta solo recibe los datos que aprobás en la pantalla de consentimiento (como tu nombre o correo), nada más. Nexo ID es de código abierto y se puede autoalojar.',
        ],
        [
            'q' => '¿Cómo cierro sesión o gestiono mis sesiones?',
            'a' => 'Entrá a «Tu cuenta». Ahí podés cerrar sesión y, en «Sesiones activas», ver todos los dispositivos con sesión iniciada y cerrar cualquiera de ellos (o todas las demás sesiones) si no reconocés alguno.',
        ],
        [
            'q' => 'Olvidé mi contraseña, ¿cómo la restablezco?',
            'a' => 'En la página de inicio de sesión usá «¿Olvidaste tu contraseña?» e ingresá tu correo. Te enviamos un enlace para elegir una nueva contraseña; por seguridad, el enlace caduca al poco tiempo.',
        ],
    ],
];
