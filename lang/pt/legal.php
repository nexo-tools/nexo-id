<?php

// Páginas legais (privacidade + termos) do Nexo ID, renderizadas por legal/show.
//
// NÃO foi revisado por um advogado. Foi escrito para descrever com precisão o que
// este código realmente faz — a parte que qualquer pessoa pode verificar lendo o
// repositório — para que uma revisão jurídica, se o operador quiser uma, comece de
// algo verdadeiro e não de um modelo sobre dados que a aplicação nunca toca.
//
// O espanhol (lang/es/legal.php) é o idioma fonte deste conteúdo; isto é a tradução.
return [
    'updated' => 'Última atualização: 28 de julho de 2026',

    'operator' => [
        'h' => 'Quem opera esta instância',
        'p' => 'Esta instância é operada por :operator.',
        'contact' => 'Você pode escrever para :contact.',
    ],

    'privacy' => [
        'title' => 'Privacidade',
        'intro' => 'O Nexo ID é o serviço de identidade do ecossistema Nexo: uma única conta para entrar em todas as ferramentas Nexo. É open source e auto-hospedável, e esta política descreve o que esta instância faz. Guardamos o mínimo necessário para identificar você e nada mais: não há cookies de rastreamento, nem análise de terceiros, nem publicidade.',
        'sections' => [
            [
                'h' => 'O que guardamos da sua conta',
                'p' => 'Seu nome de exibição, seu e-mail e sua senha convertida em um hash irreversível: ninguém consegue lê-la, nem quem opera esta instância. Guardamos também a data em que você verificou o e-mail e o idioma escolhido. O e-mail é armazenado normalizado (sem espaços e em minúsculas) para que o mesmo endereço não possa se cadastrar duas vezes.',
            ],
            [
                'h' => 'Verificação do e-mail',
                'p' => 'Ao se cadastrar enviamos um link assinado e com prazo de validade. Até você confirmá-lo não consegue acessar seu perfil nem autorizar nenhuma ferramenta a usar sua conta: a verificação é o que faz sua identidade valer no resto do ecossistema.',
            ],
            [
                'h' => 'Suas sessões',
                'p' => 'De cada início de sessão guardamos um identificador de sessão, o endereço IP e o navegador de onde você entrou, além da data da última atividade. Você pode ver todas em «Sua conta» e encerrar qualquer uma delas, ou todas as outras de uma vez. Elas existem para que você reconheça um acesso que não é seu, e são apagadas ao sair ou ao expirar.',
            ],
            [
                'h' => 'O que uma ferramenta recebe quando você a autoriza',
                'p' => 'Quando você entra em uma ferramenta Nexo com sua conta, essa ferramenta recebe apenas os dados do escopo concedido: sempre um identificador da sua conta (o campo «sub») e — se pedir o escopo de perfil ou o de e-mail — seu nome de exibição («name»), seu e-mail («email») e se ele está verificado («email_verified»). Ela nunca recebe sua senha, suas sessões, seu endereço IP nem a lista das outras ferramentas que você usa.',
            ],
            [
                'h' => 'Seu identificador é o mesmo em todas as ferramentas',
                'p' => 'O identificador que as ferramentas recebem é o da sua conta e nunca muda: é o que faz você continuar sendo você quando volta, e o que torna possível «uma conta para tudo». A contrapartida honesta é que duas ferramentas que comparem esse valor podem deduzir que você é a mesma pessoa. Se preferir evitar isso, use contas separadas ou o modo standalone de cada ferramenta, que funciona sem o Nexo ID.',
            ],
            [
                'h' => 'O que guardamos das autorizações',
                'p' => 'Guardamos qual aplicação você autorizou, com quais escopos e até quando, em forma de códigos e tokens ligados à sua conta; eles expiram sozinhos e podem ser revogados. As ferramentas do próprio ecossistema Nexo são clientes de primeira parte e não mostram tela de consentimento, porque quem opera esta instância as registrou; uma aplicação de terceiros pede sua autorização. Ainda não existe uma tela no seu perfil para revisar e revogar esses acessos: se quiser cortar algum, escreva para o contato desta instância.',
            ],
            [
                'h' => 'Cookies',
                'p' => 'Apenas os necessários para o serviço funcionar: o de sessão (criptografado), o de proteção contra CSRF e duas preferências compartilhadas com o resto do ecossistema — «nexo-lang» para o idioma e «nexo-theme» para o tema claro/escuro —, que ficam sem criptografia justamente para que todas as ferramentas possam lê-las e não contêm nenhum dado seu. Se você marcar «Lembrar-me», é adicionado um cookie com um token aleatório para não pedir sua senha a cada visita. Nenhum deles serve para publicidade ou rastreamento.',
            ],
            [
                'h' => 'E-mails que enviamos',
                'p' => 'Somente os da conta: verificação de e-mail, redefinição de senha e o aviso que chega quando sua senha muda. Saem pelo provedor de e-mail que esta instância tiver configurado, que necessariamente processa o endereço de destino e o conteúdo da mensagem para poder entregá-la. Não enviamos newsletters nem promoções.',
            ],
            [
                'h' => 'Segurança e limites de uso',
                'p' => 'Contamos as tentativas de login malsucedidas por combinação de e-mail e endereço IP para bloquear temporariamente ataques de força bruta, e aplicamos limites por IP às requisições sensíveis. Esses contadores são temporários, ficam no cache e expiram sozinhos.',
            ],
            [
                'h' => 'Métricas',
                'p' => 'Esta instância pode ativar o contador de visitas do ecossistema, que envia um sinal anônimo — ferramenta e rota, nada mais — sem cookies e respeitando o «Do Not Track». Vem desativado de fábrica e não identifica ninguém.',
            ],
            [
                'h' => 'Cada ferramenta tem sua própria política',
                'p' => 'O Nexo ID cuida apenas da sua identidade. O que cada ferramenta faz com o que você cria dentro dela — seus links, suas reservas, seus eventos — é explicado pela política de privacidade daquela ferramenta, não por esta.',
            ],
            [
                'h' => 'Por quanto tempo guardamos os dados',
                'p' => 'Sua conta e seus dados são mantidos enquanto a conta existir. As sessões e os tokens expiram sozinhos; os links de verificação e de redefinição de senha expiram em poucos minutos e são de uso único.',
            ],
            [
                'h' => 'Seus direitos',
                'p' => 'Seu nome de exibição e sua senha você mesmo altera em «Sua conta». Para pedir acesso aos seus dados, sua correção ou a exclusão da conta, escreva para quem opera esta instância (o contato está abaixo e na página de ajuda).',
            ],
            [
                'h' => 'Outras instâncias',
                'p' => 'O Nexo ID pode ser instalado em qualquer servidor. Cada instalação é independente e responde pelos seus próprios dados: esta política fala apenas desta instância.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Termos de uso',
        'intro' => 'Ao usar esta instância do Nexo ID você aceita o que segue. É um serviço gratuito, oferecido no estado em que se encontra.',
        'sections' => [
            [
                'h' => 'O que é o serviço',
                'p' => 'O Nexo ID é um provedor de identidade: você cria uma conta aqui e a usa para entrar nas ferramentas do ecossistema Nexo por meio de OAuth 2.0 com PKCE e OpenID Connect. Ele não hospeda o conteúdo que você cria dentro de cada ferramenta e não é um serviço de e-mail ou de arquivos.',
            ],
            [
                'h' => 'Sua conta',
                'p' => 'Você precisa de um e-mail real e deve verificá-lo para usar a conta com as ferramentas. É responsável por manter sua senha segura e pelo que for feito a partir das suas sessões. Se suspeitar de um acesso alheio, troque a senha e encerre as demais sessões pelo seu perfil. Uma conta é de uma pessoa: não a compartilhe.',
            ],
            [
                'h' => 'O que você autoriza ao entrar em uma ferramenta',
                'p' => 'Usar o Nexo ID para entrar em uma ferramenta dá a ela acesso aos dados do escopo solicitado: seu identificador, seu nome de exibição, seu e-mail e o estado de verificação dele. A partir daí, essa ferramenta trata esses dados sob os próprios termos e a própria política de privacidade; o Nexo ID não responde pelo que ela fizer com eles.',
            ],
            [
                'h' => 'Uso indevido',
                'p' => 'Não é permitido se passar por outra pessoa, criar contas de forma automatizada, testar credenciais alheias, contornar os limites de uso, nem atacar o serviço ou as ferramentas que dependem dele. Quem opera esta instância pode suspender uma conta que faça qualquer uma dessas coisas.',
            ],
            [
                'h' => 'Aplicações cliente',
                'p' => 'Registrar uma aplicação nesta instância é decisão de quem a opera. Uma aplicação registrada deve pedir apenas os escopos de que precisa e usar os dados para aquilo que o usuário autorizou; o registro pode ser revogado a qualquer momento.',
            ],
            [
                'h' => 'Disponibilidade',
                'p' => 'O serviço é oferecido sem garantias de disponibilidade. Lembre-se de que, se o Nexo ID estiver fora do ar, você também não conseguirá entrar nas ferramentas que dependem dele, mesmo que elas estejam no ar.',
            ],
            [
                'h' => 'Limite de responsabilidade',
                'p' => 'Quem opera esta instância não se responsabiliza por danos decorrentes do uso do serviço, incluindo acessos que não funcionem, interrupções ou perda de dados.',
            ],
            [
                'h' => 'Software livre',
                'p' => 'O Nexo ID é distribuído sob licença MIT: você pode ler o código, modificá-lo e hospedar sua própria instância. O software é entregue sem garantias, conforme essa licença indica.',
            ],
            [
                'h' => 'Mudanças',
                'p' => 'Estes termos podem mudar. A data acima indica a última atualização.',
            ],
        ],
    ],
];
