<?php

// Páginas legales (privacidad + términos) de Nexo ID, renderizadas por legal/show.
//
// NO está revisado por un abogado. Está escrito para describir con precisión lo
// que este código hace de verdad — que es la parte que se puede verificar leyendo
// el repo — de modo que una revisión legal, si el operador la quiere, arranque de
// algo cierto y no de un clausulado sobre datos que la app nunca toca.
//
// El español es el idioma fuente de este contenido; en/pt son su traducción.
return [
    'updated' => 'Última actualización: 28 de julio de 2026',

    'operator' => [
        'h' => 'Quién opera esta instancia',
        'p' => 'Esta instancia la opera :operator.',
        'contact' => 'Podés escribir a :contact.',
    ],

    'privacy' => [
        'title' => 'Privacidad',
        'intro' => 'Nexo ID es el servicio de identidad del ecosistema Nexo: una sola cuenta para entrar a todas las herramientas Nexo. Es open source y autoalojable, y esta política describe qué hace esta instancia. Guardamos lo mínimo para poder identificarte y nada más: no hay cookies de seguimiento, ni analítica de terceros, ni publicidad.',
        'sections' => [
            [
                'h' => 'Qué guardamos de tu cuenta',
                'p' => 'Tu nombre visible, tu correo electrónico y tu contraseña convertida en un hash irreversible: nadie puede leerla, tampoco quien opera esta instancia. Guardamos además la fecha en que verificaste el correo y el idioma que elegiste. El correo se almacena normalizado (sin espacios y en minúsculas) para que una misma dirección no pueda registrarse dos veces.',
            ],
            [
                'h' => 'Verificación del correo',
                'p' => 'Al registrarte te enviamos un enlace firmado y con caducidad. Hasta que lo confirmes no podés entrar a tu perfil ni autorizar a ninguna herramienta a usar tu cuenta: la verificación es lo que hace que tu identidad valga en el resto del ecosistema.',
            ],
            [
                'h' => 'Tus sesiones',
                'p' => 'De cada inicio de sesión guardamos un identificador de sesión, la dirección IP y el navegador desde el que entraste, y la fecha de la última actividad. Podés verlas todas en «Tu cuenta» y cerrar cualquiera de ellas, o todas las demás a la vez. Existen para que reconozcas un acceso que no sea tuyo, y se borran al cerrar sesión o al caducar.',
            ],
            [
                'h' => 'Qué recibe una herramienta cuando la autorizás',
                'p' => 'Cuando entrás a una herramienta Nexo con tu cuenta, esa herramienta recibe únicamente los datos del permiso que le corresponde: siempre un identificador de tu cuenta (el campo «sub»), y —si pide el permiso de perfil o el de correo— tu nombre visible («name»), tu correo electrónico («email») y si está verificado o no («email_verified»). Nunca recibe tu contraseña, tus sesiones, tu dirección IP ni la lista de las otras herramientas que usás.',
            ],
            [
                'h' => 'Tu identificador es el mismo en todas las herramientas',
                'p' => 'El identificador que reciben las herramientas es el de tu cuenta y no cambia nunca: es lo que permite que sigas siendo vos cuando volvés, y lo que hace posible «una cuenta para todo». La contrapartida honesta es que dos herramientas que comparen ese valor pueden deducir que sos la misma persona. Si preferís evitarlo, usá cuentas separadas o el modo standalone de cada herramienta, que funciona sin Nexo ID.',
            ],
            [
                'h' => 'Qué guardamos de las autorizaciones',
                'p' => 'Guardamos qué aplicación autorizaste, con qué permisos y hasta cuándo, en forma de códigos y tokens asociados a tu cuenta; caducan solos y se pueden revocar. Las herramientas del propio ecosistema Nexo son de primera parte y no muestran pantalla de consentimiento, porque las registra quien opera esta instancia; una aplicación de terceros sí te pide permiso. Todavía no hay una pantalla en tu perfil para revisar y revocar esos accesos: si querés cortar uno, escribí al contacto de esta instancia.',
            ],
            [
                'h' => 'Cookies',
                'p' => 'Solo las necesarias para que el servicio funcione: la de sesión (cifrada), la de protección contra CSRF, y dos preferencias compartidas con el resto del ecosistema —«nexo-lang» para el idioma y «nexo-theme» para el tema claro/oscuro—, que van sin cifrar justamente para que todas las herramientas puedan leerlas y no contienen ningún dato tuyo. Si marcás «Recordarme» se añade una cookie con un testigo aleatorio para no pedirte la contraseña en cada visita. Ninguna sirve para publicidad ni para seguimiento.',
            ],
            [
                'h' => 'Correos que te enviamos',
                'p' => 'Solo los de la cuenta: verificación del correo, restablecimiento de contraseña y el aviso que te llega cuando tu contraseña cambia. Salen por el proveedor de correo que tenga configurado esta instancia, que necesariamente procesa la dirección de destino y el contenido del mensaje para poder entregarlo. No enviamos boletines ni promociones.',
            ],
            [
                'h' => 'Seguridad y límites de uso',
                'p' => 'Contamos los intentos fallidos de inicio de sesión por combinación de correo y dirección IP para bloquear temporalmente los ataques de fuerza bruta, y aplicamos límites por IP a las peticiones sensibles. Esos contadores son temporales, viven en la caché y caducan solos.',
            ],
            [
                'h' => 'Métricas',
                'p' => 'Esta instancia puede activar el contador de visitas del ecosistema, que envía una señal anónima —herramienta y ruta, nada más— sin cookies y respetando «Do Not Track». Viene desactivado de fábrica y no identifica a nadie.',
            ],
            [
                'h' => 'Cada herramienta tiene su propia política',
                'p' => 'Nexo ID solo se ocupa de tu identidad. Lo que cada herramienta haga con lo que crees dentro de ella —tus enlaces, tus reservas, tus eventos— lo explica la política de privacidad de esa herramienta, no ésta.',
            ],
            [
                'h' => 'Cuánto tiempo conservamos los datos',
                'p' => 'Tu cuenta y sus datos se conservan mientras la cuenta exista. Las sesiones y los tokens caducan solos; los enlaces de verificación y de restablecimiento de contraseña caducan a los pocos minutos y son de un solo uso.',
            ],
            [
                'h' => 'Tus derechos',
                'p' => 'Tu nombre visible y tu contraseña los cambiás vos desde «Tu cuenta». Para pedir acceso a tus datos, su corrección o el borrado de tu cuenta, escribí a quien opera esta instancia (el contacto está más abajo y en la página de ayuda).',
            ],
            [
                'h' => 'Otras instancias',
                'p' => 'Nexo ID se puede instalar en cualquier servidor. Cada instalación es independiente y responde por sus propios datos: esta política habla solo de esta instancia.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Términos de uso',
        'intro' => 'Al usar esta instancia de Nexo ID aceptás lo que sigue. Es un servicio gratuito, ofrecido tal cual está.',
        'sections' => [
            [
                'h' => 'Qué es el servicio',
                'p' => 'Nexo ID es un proveedor de identidad: creás una cuenta acá y la usás para entrar a las herramientas del ecosistema Nexo mediante OAuth 2.0 con PKCE y OpenID Connect. No aloja el contenido que crees dentro de cada herramienta ni actúa como servicio de correo o de archivos.',
            ],
            [
                'h' => 'Tu cuenta',
                'p' => 'Necesitás un correo real y verificarlo para poder usar la cuenta con las herramientas. Sos responsable de mantener tu contraseña a salvo y de lo que se haga desde tus sesiones. Si sospechás de un acceso ajeno, cambiá la contraseña y cerrá las demás sesiones desde tu perfil. Una cuenta es de una persona: no la compartas.',
            ],
            [
                'h' => 'Qué autorizás al entrar a una herramienta',
                'p' => 'Al usar Nexo ID para entrar a una herramienta le estás dando acceso a los datos del permiso solicitado: tu identificador, tu nombre visible, tu correo y su estado de verificación. Desde ahí, esa herramienta trata esos datos bajo sus propios términos y su propia política de privacidad; Nexo ID no responde por lo que haga con ellos.',
            ],
            [
                'h' => 'Uso indebido',
                'p' => 'No se permite suplantar a otra persona, crear cuentas de forma automatizada, probar credenciales ajenas, esquivar los límites de uso, ni atacar el servicio o a las herramientas que dependen de él. Quien opera esta instancia puede suspender una cuenta que haga cualquiera de esas cosas.',
            ],
            [
                'h' => 'Aplicaciones cliente',
                'p' => 'Registrar una aplicación contra esta instancia lo decide quien la opera. Una aplicación registrada debe pedir solo los permisos que necesita y usar los datos para lo que el usuario autorizó; el registro se puede revocar en cualquier momento.',
            ],
            [
                'h' => 'Disponibilidad',
                'p' => 'El servicio se ofrece sin garantías de disponibilidad. Tené en cuenta que si Nexo ID no está disponible tampoco vas a poder iniciar sesión en las herramientas que dependen de él, aunque ellas sí lo estén.',
            ],
            [
                'h' => 'Límite de responsabilidad',
                'p' => 'Quien opera esta instancia no se hace responsable de los daños derivados del uso del servicio, incluidos accesos que no funcionen, interrupciones o pérdidas de datos.',
            ],
            [
                'h' => 'Software libre',
                'p' => 'Nexo ID se distribuye con licencia MIT: podés leer el código, modificarlo y alojar tu propia instancia. El software se entrega sin garantías, según indica esa licencia.',
            ],
            [
                'h' => 'Cambios',
                'p' => 'Estos términos pueden cambiar. La fecha de arriba indica la última actualización.',
            ],
        ],
    ],
];
