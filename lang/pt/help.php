<?php

// Conteúdo da central de ajuda do Nexo ID (help/index via __('help.faqs')).
return [
    'meta_description' => 'Ajuda do Nexo ID: o que é o login único, para que serve a sua conta, segurança e privacidade, e como gerenciar suas sessões.',
    'faqs' => [
        [
            'q' => 'O que é o Nexo ID?',
            'a' => 'O Nexo ID é o login único do ecossistema Nexo: uma só conta para entrar em todas as ferramentas Nexo, em vez de uma senha diferente para cada aplicativo.',
        ],
        [
            'q' => 'Para que serve?',
            'a' => 'Você faz login uma vez com o Nexo ID e acessa qualquer ferramenta Nexo com a mesma conta. Quando uma ferramenta pede para entrar, você autoriza uma vez em uma tela de consentimento e pronto.',
        ],
        [
            'q' => 'É seguro? E a minha privacidade?',
            'a' => 'Sua senha é guardada com hash, nunca em texto puro. Cada login é uma sessão de dispositivo que você pode revisar e encerrar. Uma ferramenta só recebe os dados que você aprova na tela de consentimento (como seu nome ou e-mail), nada mais. O Nexo ID é de código aberto e pode ser auto-hospedado.',
        ],
        [
            'q' => 'Como encerro a sessão ou gerencio minhas sessões?',
            'a' => 'Acesse "Sua conta". Ali você pode sair e, em "Sessões ativas", ver todos os dispositivos com sessão iniciada e encerrar qualquer um deles (ou todas as outras sessões) se não reconhecer algum.',
        ],
        [
            'q' => 'Esqueci minha senha — como redefinir?',
            'a' => 'Na página de login use "Esqueceu sua senha?" e informe seu e-mail. Enviamos um link para escolher uma nova senha; por segurança, o link expira em pouco tempo.',
        ],
    ],
];
