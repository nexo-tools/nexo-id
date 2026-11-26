<?php

// Contenido del centro de ayuda de Nexo ID (help/index vía __('help.faqs')).
// Lo que dice tiene que ser lo que pasa: las herramientas Nexo son de primera
// parte y entran sin pantalla de permiso (OauthClient::skipsAuthorization);
// solo una aplicación de terceros la muestra (H-nexoid-11, HelpContentTest).
return [
    'meta_description' => 'Ayuda de Nexo ID: qué es el inicio de sesión único, para qué sirve tu cuenta, seguridad y privacidad, cómo gestionar tus sesiones y cómo borrar tu cuenta.',
    'faqs' => [
        [
            'q' => '¿Qué es Nexo ID?',
            'a' => 'Nexo ID es el inicio de sesión único del ecosistema Nexo: una sola cuenta para entrar a todas las herramientas Nexo, en lugar de una contraseña distinta por cada aplicación.',
        ],
        [
            'q' => '¿Para qué sirve?',
            'a' => 'Inicias sesión una vez con Nexo ID y entras a cualquier herramienta Nexo con la misma cuenta. Las herramientas Nexo entran directo, sin pedirte permiso, porque son parte del mismo ecosistema. Una aplicación de otra empresa, en cambio, te muestra primero qué datos quiere y te pide permiso.',
        ],
        [
            'q' => '¿Es seguro? ¿Y mi privacidad?',
            'a' => 'Tu contraseña se guarda cifrada, nunca tal cual. Cada inicio de sesión es una sesión de dispositivo que puedes revisar y cerrar. Una herramienta Nexo recibe solo tu identificador de cuenta, tu nombre visible y tu correo; nunca tu contraseña, tus sesiones ni la lista de las otras herramientas que usas. Nexo ID es de código abierto y se puede instalar en un servidor propio.',
        ],
        [
            'q' => '¿Cómo cierro sesión o gestiono mis sesiones?',
            'a' => '«Cerrar sesión», en el menú de tu cuenta, cierra este dispositivo en Nexo ID y en todas las herramientas Nexo que abriste con él. En «Tu cuenta», en «Sesiones activas», ves cada dispositivo con su navegador, su última actividad y las herramientas donde se usó, y puedes cerrar uno («Cerrar esta sesión»), todos los demás o todos, incluido este.',
        ],
        [
            'q' => '¿Dónde veo las herramientas que usan mi cuenta?',
            'a' => 'En «Tu cuenta», en «Herramientas conectadas»: cada herramienta Nexo donde usaste esta cuenta, cuándo fue la última vez y un botón para abrirla.',
        ],
        [
            'q' => '¿Cómo borro mi cuenta?',
            'a' => 'En «Tu cuenta», al final, en «Borrar tu cuenta», con tu contraseña. Se borran tu nombre, tu correo, tu contraseña y tus sesiones, y se cierra tu sesión en todas las herramientas Nexo. Lo que tengas en cada herramienta no se borra con esto: si quieres borrarlo, hazlo antes en cada una, porque después ya no podrás entrar con esta cuenta.',
        ],
        [
            'q' => 'Olvidé mi contraseña, ¿cómo la restablezco?',
            'a' => 'En la página de inicio de sesión usa «¿Olvidaste tu contraseña?» e ingresa tu correo. Te enviamos un enlace para elegir una nueva contraseña; por seguridad, el enlace caduca al poco tiempo.',
        ],
    ],
];
