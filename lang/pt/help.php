<?php

// Conteúdo da central de ajuda do Nexo ID (help/index via __('help.faqs')).
// O que diz tem que ser o que acontece: as ferramentas Nexo são de primeira
// parte e entram sem tela de permissão (OauthClient::skipsAuthorization); só um
// aplicativo de terceiros mostra uma (H-nexoid-11, HelpContentTest).
return [
    'meta_description' => 'Ajuda do Nexo ID: o que é o login único, para que serve a sua conta, segurança e privacidade, como gerenciar suas sessões e como excluir sua conta.',
    'faqs' => [
        [
            'q' => 'O que é o Nexo ID?',
            'a' => 'O Nexo ID é o login único do ecossistema Nexo: uma só conta para entrar em todas as ferramentas Nexo, em vez de uma senha diferente para cada aplicativo.',
        ],
        [
            'q' => 'Para que serve?',
            'a' => 'Você faz login uma vez com o Nexo ID e entra em qualquer ferramenta Nexo com a mesma conta. As ferramentas Nexo entram direto, sem pedir permissão, porque fazem parte do mesmo ecossistema. Um aplicativo de outra empresa, por outro lado, mostra primeiro quais dados quer e pede a sua permissão.',
        ],
        [
            'q' => 'É seguro? E a minha privacidade?',
            'a' => 'Sua senha é guardada criptografada, nunca como está. Cada login é uma sessão de dispositivo que você pode revisar e encerrar. Uma ferramenta Nexo recebe só o identificador da sua conta, seu nome de exibição e seu e-mail; nunca sua senha, suas sessões nem a lista das outras ferramentas que você usa. O Nexo ID é de código aberto e pode ser instalado em um servidor próprio.',
        ],
        [
            'q' => 'Como encerro a sessão ou gerencio minhas sessões?',
            'a' => '"Sair", no menu da sua conta, encerra este dispositivo no Nexo ID e em todas as ferramentas Nexo que você abriu com ele. Em "Sua conta", em "Sessões ativas", você vê cada dispositivo com o navegador, a última atividade e as ferramentas em que foi usado, e pode encerrar um ("Encerrar esta sessão"), todos os outros ou todos, incluindo este.',
        ],
        [
            'q' => 'Onde vejo as ferramentas que usam a minha conta?',
            'a' => 'Em "Sua conta", em "Ferramentas conectadas": cada ferramenta Nexo em que você usou esta conta, quando foi a última vez e um botão para abri-la.',
        ],
        [
            'q' => 'Como excluo a minha conta?',
            'a' => 'Em "Sua conta", no final, em "Excluir sua conta", com a sua senha. São excluídos seu nome, seu e-mail, sua senha e suas sessões, e todas as ferramentas Nexo encerram a sua sessão. O que você tem em cada ferramenta não é excluído com isso: se quiser excluir, faça antes em cada uma, porque depois não vai conseguir entrar com esta conta.',
        ],
        [
            'q' => 'Esqueci minha senha — como redefinir?',
            'a' => 'Na página de login use "Esqueceu sua senha?" e informe seu e-mail. Enviamos um link para escolher uma nova senha; por segurança, o link expira em pouco tempo.',
        ],
    ],
];
