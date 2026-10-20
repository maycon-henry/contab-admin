@php
    // Ícones (Heroicons outline) usados na página
    $icone = [
        'calendario' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        'documento' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'usuario' => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
        'sino' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
        'check' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'grafico' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
        'escudo' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'predio' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
        'nuvem' => 'M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z',
        'x' => 'M6 18 18 6M6 6l12 12',
        'seta' => 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        'menu' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
        'cadeado' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        'celular' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
    ];
@endphp

<x-layouts.publico titulo="Contab Admin — o escritório contábil organizado mês a mês">

    {{-- ================================================================ CABEÇALHO --}}
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="#inicio" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-[#1f3a5f]">
                    <svg class="h-5 w-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-[#1f3a5f]">Contab Admin</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 lg:flex">
                <a href="#recursos" class="hover:text-[#1f3a5f]">Recursos</a>
                <a href="#como-funciona" class="hover:text-[#1f3a5f]">Como funciona</a>
                <a href="#portal" class="hover:text-[#1f3a5f]">Portal da empresa</a>
                <a href="#planos" class="hover:text-[#1f3a5f]">Planos</a>
                <a href="#duvidas" class="hover:text-[#1f3a5f]">Dúvidas</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    {{-- RN-002 — usuário autenticado vai direto ao painel --}}
                    <a href="{{ auth()->user()->urlInicial() }}" class="rounded-lg bg-[#1f3a5f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2a4d7c]">Ir para o painel</a>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-[#1f3a5f] hover:bg-slate-100 sm:block">Entrar</a>
                    <a href="#contato" class="rounded-lg bg-[#1f3a5f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#2a4d7c]"><span class="sm:hidden">Demonstração</span><span class="hidden sm:inline">Agendar demonstração</span></a>
                @endauth

                <details class="relative lg:hidden">
                    <summary class="list-none cursor-pointer rounded-lg p-2 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                        <svg class="h-6 w-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['menu'] }}" /></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        @foreach (['recursos' => 'Recursos', 'como-funciona' => 'Como funciona', 'portal' => 'Portal da empresa', 'planos' => 'Planos', 'duvidas' => 'Dúvidas'] as $ancora => $rotulo)
                            <a href="#{{ $ancora }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">{{ $rotulo }}</a>
                        @endforeach
                        @guest
                            <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-[#1f3a5f] hover:bg-slate-50">Entrar</a>
                        @endguest
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main id="inicio">

        {{-- ================================================================ HERO --}}
        <section class="relative overflow-hidden bg-gradient-to-b from-[#10233d] via-[#1f3a5f] to-[#24456f] text-white">
            <div class="pointer-events-none absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, rgba(56,189,248,.35), transparent 40%), radial-gradient(circle at 85% 60%, rgba(16,185,129,.25), transparent 45%);"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-6 py-20 lg:grid-cols-2 lg:py-28 [&>*]:min-w-0">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-sky-300/30 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-sky-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Feito para escritórios de contabilidade
                    </span>

                    <h1 class="mt-6 text-4xl font-bold leading-[1.1] tracking-tight md:text-5xl xl:text-6xl">
                        Pare de correr atrás de documento.
                        <span class="block bg-gradient-to-r from-sky-300 to-emerald-300 bg-clip-text text-transparent">Deixe o sistema cobrar por você.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-sky-100/90">
                        Suas empresas enviam notas, extratos, certidões e admissões em um portal próprio. O escritório confere tudo mês a mês, e quem estiver atrasado recebe a cobrança automaticamente.
                    </p>

                    <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                        <a href="#contato" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-400 px-6 py-3.5 text-base font-semibold text-[#0f2240] shadow-lg shadow-sky-500/20 hover:bg-sky-300">
                            Agendar demonstração gratuita
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['seta'] }}" /></svg>
                        </a>
                        <a href="#como-funciona" class="inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 text-base font-semibold text-white hover:bg-white/10">
                            Ver como funciona
                        </a>
                    </div>

                    <ul class="mt-10 grid gap-3 text-sm text-sky-100 sm:grid-cols-3">
                        @foreach (['Sem instalação: 100% na nuvem', 'Portal incluso para as empresas', 'Dados isolados e protegidos (LGPD)'] as $item)
                            <li class="flex items-center gap-2">
                                <svg class="h-5 w-5 shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['check'] }}" /></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Ilustração do produto (painel do administrador) --}}
                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl bg-sky-400/10 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-white text-slate-700 shadow-2xl">
                        <div class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-3">
                            <span class="h-3 w-3 rounded-full bg-rose-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                            <span class="ml-3 text-xs text-slate-400">Painel do escritório</span>
                        </div>
                        <div class="grid grid-cols-[96px_minmax(0,1fr)] sm:grid-cols-[110px_minmax(0,1fr)]">
                            <div class="space-y-2 border-r border-slate-100 bg-slate-50/60 p-3 text-[11px] font-medium text-slate-500">
                                <div class="rounded-md bg-sky-100 px-2 py-1.5 text-sky-800">Início</div>
                                <div class="px-2 py-1">Áreas de trabalho</div>
                                <div class="flex justify-between px-2 py-1">Avaliação <span class="rounded bg-sky-600 px-1.5 text-white">11</span></div>
                                <div class="px-2 py-1">Notas fiscais</div>
                                <div class="flex justify-between px-2 py-1">Admissões <span class="rounded bg-amber-500 px-1.5 text-white">2</span></div>
                                <div class="px-2 py-1">Empresas</div>
                            </div>
                            <div class="space-y-3 p-4">
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="rounded-lg border border-slate-100 p-2.5"><p class="text-[10px] text-slate-400">Empresas</p><p class="text-xl font-bold text-slate-800">128</p></div>
                                    <div class="rounded-lg border border-rose-100 bg-rose-50 p-2.5"><p class="text-[10px] text-rose-500">Devendo docs</p><p class="text-xl font-bold text-rose-600">9</p></div>
                                    <div class="rounded-lg border border-sky-100 bg-sky-50 p-2.5"><p class="text-[10px] text-sky-600">Em avaliação</p><p class="text-xl font-bold text-sky-700">23</p></div>
                                </div>
                                <div class="rounded-lg border border-slate-100">
                                    <p class="border-b border-slate-100 px-3 py-2 text-[11px] font-semibold text-slate-600">Empresas devendo documentação</p>
                                    @foreach ([['Construtora Horizonte', '5 docs', '41 dias'], ['Mercado Bom Preço', '3 docs', '12 dias'], ['Clínica Vida Plena', '2 docs', '6 dias']] as [$nome, $docs, $dias])
                                        <div class="flex items-center justify-between border-b border-slate-50 px-3 py-2 text-[11px] last:border-0">
                                            <span class="font-medium text-slate-700">{{ $nome }}</span>
                                            <span class="text-rose-600">{{ $docs }}</span>
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-700">{{ $dias }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-[11px] text-emerald-800">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['sino'] }}" /></svg>
                                    Cobrança diária enviada às 8h para 9 empresas
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- cartão flutuante: nota importada --}}
                    <div class="absolute -bottom-6 -left-4 hidden rounded-xl border border-slate-200 bg-white p-3 text-slate-700 shadow-xl sm:block">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">XML importado</p>
                        <p class="text-sm font-semibold">NF-e 1.024 · Saída</p>
                        <p class="text-xs text-emerald-600">Organizada em 09/2026 automaticamente</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================ NÚMEROS DO PRODUTO --}}
        <section class="border-b border-slate-100 bg-white">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-6 py-12 text-center md:grid-cols-4">
                @foreach ([['1 lugar', 'para documentos, notas e admissões'], ['8h', 'cobrança automática em todo dia útil'], ['0', 'planilhas de controle de pendências'], ['100%', 'isolado por escritório e por empresa']] as [$numero, $texto])
                    <div>
                        <p class="text-3xl font-bold text-[#1f3a5f] md:text-4xl">{{ $numero }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ================================================================ PROBLEMA x SOLUÇÃO --}}
        <section class="bg-slate-50 py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Chega de improviso</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Sua equipe ainda faz isso todo mês?</h2>
                </div>

                <div class="mt-14 grid gap-8 lg:grid-cols-2">
                    <div class="rounded-2xl border border-rose-100 bg-white p-8">
                        <p class="text-sm font-bold uppercase tracking-wider text-rose-500">Hoje</p>
                        <ul class="mt-6 space-y-4">
                            @foreach ([
                                'Documentos chegando por WhatsApp, e-mail e pendrive, sem saber de qual mês são.',
                                'Alguém da equipe perde horas cobrando empresa por empresa.',
                                'Admissão chega sem comprovante, sem exame, em cima do prazo do eSocial.',
                                'Ninguém sabe ao certo quem está atrasado, nem há quanto tempo.',
                                'Notas fiscais salvas em pastas, separadas à mão por mês.',
                            ] as $dor)
                                <li class="flex gap-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['x'] }}" /></svg>
                                    <span>{{ $dor }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-white p-8 shadow-sm ring-1 ring-emerald-100">
                        <p class="text-sm font-bold uppercase tracking-wider text-emerald-600">Com o Contab Admin</p>
                        <ul class="mt-6 space-y-4">
                            @foreach ([
                                'Cada empresa envia tudo pelo portal, já no item e no mês certos.',
                                'O sistema cobra sozinho, todo dia útil, só quem tem pendência.',
                                'A admissão só chega ao escritório com todos os documentos anexados.',
                                'Um painel mostra quem deve, o quê e há quantos dias.',
                                'O XML da nota é lido e organizado por mês, entrada e saída — automaticamente.',
                            ] as $ganho)
                                <li class="flex gap-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['check'] }}" /></svg>
                                    <span class="text-slate-800">{{ $ganho }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================ RECURSOS --}}
        <section id="recursos" class="scroll-mt-20 py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Recursos</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Tudo o que o escritório precisa para fechar o mês em dia</h2>
                    <p class="mt-4 text-lg text-slate-500">Pensado a partir da rotina real de um escritório contábil — do pedido do documento até o fechamento da competência.</p>
                </div>

                <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['calendario', 'Área de trabalho mensal', 'Cada empresa tem o seu mês de trabalho, com a lista do que precisa entregar, prazo de cada item e progresso. Troque de mês com um clique.'],
                        ['documento', 'Notas fiscais por XML', 'A empresa envia os XMLs (ou um .zip). O sistema identifica a data, se é entrada ou saída, recusa notas de outras empresas e evita duplicidade.'],
                        ['check', 'Avaliação de documentos', 'Aceite ou recuse com um clique, com motivo. Certidões registram validade e resultado — e certidão positiva gera alerta.'],
                        ['usuario', 'Admissão sem retrabalho', 'A empresa preenche e anexa tudo; o botão de solicitar só libera com a lista completa. O escritório analisa, devolve se precisar e conclui.'],
                        ['sino', 'Cobrança automática', 'Defina a data de corte e o sistema envia, todo dia útil às 8h, a lista do que falta para cada empresa. Sem pendência, sem e-mail.'],
                        ['grafico', 'Painel e relatórios', 'Veja quantas empresas estão devendo documentação e acompanhe quantas admissões cada colaborador iniciou e concluiu.'],
                    ] as [$ic, $titulo, $texto])
                        <div class="group rounded-2xl border border-slate-200 bg-white p-7 transition hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-lg">
                            <span class="grid h-12 w-12 place-items-center rounded-xl bg-sky-50 text-sky-600 group-hover:bg-sky-600 group-hover:text-white">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone[$ic] }}" /></svg>
                            </span>
                            <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ $titulo }}</h3>
                            <p class="mt-2 leading-relaxed text-slate-500">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ================================================================ COMO FUNCIONA --}}
        <section id="como-funciona" class="scroll-mt-20 bg-[#0f2240] py-24 text-white">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-300">Como funciona</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight md:text-4xl">Do primeiro acesso ao mês fechado em 4 passos</h2>
                </div>

                <ol class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Monte o seu catálogo', 'Defina quais documentos o escritório exige — extratos, certidões, notas — e de quais empresas. Cada escritório tem as suas regras.'],
                        ['Convide as empresas', 'Cada empresa recebe um convite por e-mail e acessa o próprio portal, vendo só o que é dela.'],
                        ['O mês se organiza sozinho', 'No dia 1º o sistema cria o mês de cada empresa. A partir da data de corte, quem tem pendência é cobrado automaticamente.'],
                        ['Confira e feche', 'Sua equipe avalia o que chegou na fila de avaliação. Com tudo aceito, o mês é fechado e fica guardado.'],
                    ] as $i => [$titulo, $texto])
                        <li class="relative rounded-2xl border border-white/10 bg-white/5 p-7">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-sky-400 text-lg font-bold text-[#0f2240]">{{ $i + 1 }}</span>
                            <h3 class="mt-5 text-lg font-semibold">{{ $titulo }}</h3>
                            <p class="mt-2 leading-relaxed text-sky-100/80">{{ $texto }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ================================================================ PORTAL DA EMPRESA --}}
        <section id="portal" class="scroll-mt-20 py-24">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
                <div class="order-2 lg:order-1">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-emerald-50 px-5 py-3">
                            <span class="text-sm font-semibold text-emerald-800">Portal da empresa · Documentos do mês</span>
                            <span class="rounded-md bg-white px-2 py-0.5 text-xs text-slate-500">09/2026</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach ([
                                ['Extrato bancário', 'Prazo 10/10', 'Recusado: arquivo ilegível', 'bg-rose-100 text-rose-700', 'Reenviar'],
                                ['Notas fiscais de saída', 'Prazo 05/10', 'Em avaliação', 'bg-sky-100 text-sky-700', null],
                                ['CND Federal', 'Prazo 15/10', 'Aceito por validade', 'bg-emerald-100 text-emerald-700', null],
                                ['Comprovantes de despesas', 'Prazo 10/10', 'Pendente', 'bg-slate-100 text-slate-600', 'Enviar'],
                            ] as [$doc, $prazo, $situacao, $cor, $botao])
                                <div class="flex items-center justify-between gap-4 px-5 py-4">
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $doc }}</p>
                                        <p class="text-xs text-slate-400">{{ $prazo }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $cor }}">{{ $situacao }}</span>
                                        @if ($botao)
                                            <span class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">{{ $botao }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <p class="text-sm font-semibold uppercase tracking-widest text-emerald-600">Portal da empresa incluso</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Seu cliente sabe exatamente o que falta — e até quando</h2>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">Cada empresa atendida ganha um acesso próprio, sem custo extra para ela. É lá que ela envia os documentos, as notas fiscais e pede as admissões.</p>
                    <ul class="mt-8 space-y-4">
                        @foreach ([
                            ['Instruções em cada documento', 'O escritório explica o que é e onde obter. Menos ligação, menos documento errado.'],
                            ['Motivo claro quando algo é recusado', 'A empresa vê o motivo e reenvia na hora, sem troca de e-mails.'],
                            ['Admissão guiada', 'Lista do que falta em tempo real e acompanhamento de cada etapa até o funcionário ser admitido.'],
                        ] as [$titulo, $texto])
                            <li class="flex gap-4">
                                <span class="mt-1 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </span>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $titulo }}</p>
                                    <p class="text-slate-500">{{ $texto }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        {{-- ================================================================ ADMISSÃO E DP --}}
        <section class="bg-slate-50 py-24">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Departamento pessoal</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Admissões que chegam completas e dentro do prazo</h2>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">O sistema conhece as regras: avisa sobre o prazo do eSocial, confere idade mínima, limite do contrato de experiência e calcula o aviso prévio proporcional no desligamento.</p>
                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['Fila de admissões', 'Ordenada pela data de início, com destaque para o que vence primeiro.'],
                            ['Responsável registrado', 'Fica gravado quem iniciou o atendimento e quem admitiu cada funcionário.'],
                            ['Devolução com motivo', 'Documento ilegível? Devolva e a empresa corrige no portal.'],
                            ['Relatório por colaborador', 'Admissões concluídas, iniciadas e tempo médio de atendimento.'],
                        ] as [$titulo, $texto])
                            <div class="rounded-xl border border-slate-200 bg-white p-5">
                                <p class="font-semibold text-slate-900">{{ $titulo }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $texto }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Linha do tempo da admissão</p>
                    <ol class="mt-6 space-y-5 border-l-2 border-slate-100 pl-6">
                        @foreach ([
                            ['bg-slate-400', 'Rascunho', 'A empresa cadastra o funcionário e anexa os documentos.'],
                            ['bg-sky-500', 'Solicitada', 'Com tudo completo, a empresa solicita. O escritório é avisado.'],
                            ['bg-amber-500', 'Em análise', 'O colaborador assume, confere cada documento e completa os dados do eSocial.'],
                            ['bg-emerald-500', 'Admitido', 'Registro concluído. A empresa recebe o aviso por e-mail.'],
                        ] as [$cor, $etapa, $texto])
                            <li class="relative">
                                <span class="absolute -left-[33px] top-1 h-4 w-4 rounded-full ring-4 ring-white {{ $cor }}"></span>
                                <p class="font-semibold text-slate-800">{{ $etapa }}</p>
                                <p class="text-sm text-slate-500">{{ $texto }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        {{-- ================================================================ SEGURANÇA --}}
        <section class="py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Segurança e LGPD</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Os dados dos seus clientes, protegidos de verdade</h2>
                </div>
                <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['escudo', 'Isolamento total', 'Cada escritório só vê os seus dados. Cada empresa só vê o que é dela.'],
                        ['cadeado', 'Dados sensíveis restritos', 'Salário e dados bancários só para quem tem permissão, com acesso registrado.'],
                        ['documento', 'Nada se perde', 'Documentos e XMLs são guardados como foram recebidos, com histórico completo.'],
                        ['nuvem', 'Acesso por convite', 'Ninguém cria conta sozinho. Bloqueio após tentativas erradas e histórico de acessos.'],
                    ] as [$ic, $titulo, $texto])
                        <div class="rounded-2xl bg-slate-50 p-6">
                            <svg class="h-7 w-7 text-[#1f3a5f]" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone[$ic] }}" /></svg>
                            <h3 class="mt-4 font-semibold text-slate-900">{{ $titulo }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ================================================================ PLANOS --}}
        {{-- Valores ainda em definição (regras.md RN-003 / decisão D2): os planos mostram "sob consulta". --}}
        <section id="planos" class="scroll-mt-20 bg-gradient-to-b from-slate-50 to-white py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Planos</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Um plano para cada tamanho de escritório</h2>
                    <p class="mt-4 text-lg text-slate-500">Todos os planos incluem o portal para as empresas, sem custo para elas.</p>
                </div>

                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    @foreach ([
                        ['Essencial', 'Para escritórios que estão organizando a rotina.', 'Até 30 empresas', false,
                            ['Área de trabalho mensal', 'Catálogo de documentos personalizado', 'Portal da empresa', 'Importação de XML (NF-e e NFC-e)', 'Cobrança automática diária']],
                        ['Profissional', 'Para escritórios em crescimento, com departamento pessoal.', 'Até 100 empresas', true,
                            ['Tudo do Essencial', 'Admissões e desligamentos', 'Fila de avaliação e de admissões', 'Painel de empresas devendo', 'Relatório por colaborador']],
                        ['Escritório+', 'Para operações com equipes maiores.', 'Mais de 100 empresas', false,
                            ['Tudo do Profissional', 'Mais administradores e colaboradores', 'Implantação assistida', 'Treinamento da equipe', 'Atendimento prioritário']],
                    ] as [$nome, $descricao, $limite, $destaque, $itens])
                        <div @class([
                            'relative flex flex-col rounded-2xl border bg-white p-8',
                            'border-[#1f3a5f] shadow-2xl ring-1 ring-[#1f3a5f] lg:-mt-4 lg:mb-4' => $destaque,
                            'border-slate-200' => ! $destaque,
                        ])>
                            @if ($destaque)
                                <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-sky-400 px-4 py-1 text-xs font-bold uppercase tracking-wider text-[#0f2240]">Recomendado</span>
                            @endif
                            <h3 class="text-xl font-bold text-slate-900">{{ $nome }}</h3>
                            <p class="mt-2 text-sm text-slate-500">{{ $descricao }}</p>
                            <p class="mt-6 text-3xl font-bold text-[#1f3a5f]">Sob consulta</p>
                            <p class="text-sm font-medium text-slate-500">{{ $limite }}</p>
                            <ul class="mt-8 flex-1 space-y-3 text-sm">
                                @foreach ($itens as $item)
                                    <li class="flex gap-2.5">
                                        <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['check'] }}" /></svg>
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                            <a href="#contato" @class([
                                'mt-8 block rounded-xl px-5 py-3 text-center font-semibold',
                                'bg-[#1f3a5f] text-white hover:bg-[#2a4d7c]' => $destaque,
                                'border border-slate-300 text-slate-800 hover:bg-slate-50' => ! $destaque,
                            ])>Falar com um especialista</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ================================================================ DÚVIDAS --}}
        <section id="duvidas" class="scroll-mt-20 py-24">
            <div class="mx-auto max-w-3xl px-6">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-600">Dúvidas frequentes</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Perguntas que todo escritório faz</h2>
                </div>
                <div class="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
                    @foreach ([
                        ['Preciso instalar alguma coisa?', 'Não. O sistema funciona no navegador, no computador ou no celular. Basta acessar com e-mail e senha.'],
                        ['As empresas pagam para usar o portal?', 'Não. O portal da empresa está incluso no plano do escritório.'],
                        ['Posso definir quais documentos cada empresa precisa enviar?', 'Sim. Cada escritório monta o seu catálogo e escolhe a quem cada documento se aplica: todas as empresas, um regime tributário ou empresas específicas.'],
                        ['Como funciona a cobrança automática?', 'Você define a data de corte (por exemplo, dia 5). A partir dela, todo dia útil às 8h, cada empresa com pendência recebe um e-mail com a lista do que falta e um link direto para enviar. Quem está em dia não recebe nada.'],
                        ['O sistema lê os XMLs das notas fiscais?', 'Sim. NF-e e NFC-e são lidas automaticamente: o sistema identifica o mês, se é entrada ou saída, recusa notas que não são da empresa e não duplica notas já enviadas.'],
                        ['O sistema envia a admissão para o eSocial?', 'Nesta versão, o sistema organiza a admissão completa, controla os prazos do eSocial e registra o recibo de envio. O envio direto ao eSocial está no nosso planejamento.'],
                        ['Meus dados ficam seguros?', 'Cada escritório tem os dados totalmente isolados, o acesso é feito por convite e dados sensíveis só são vistos por quem tem permissão, seguindo a LGPD.'],
                    ] as [$pergunta, $resposta])
                        <details class="group px-6 py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
                                {{ $pergunta }}
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-45">+</span>
                            </summary>
                            <p class="mt-3 leading-relaxed text-slate-500">{{ $resposta }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ================================================================ CONTATO (RN-004) --}}
        <section id="contato" class="scroll-mt-20 bg-gradient-to-br from-[#10233d] to-[#1f3a5f] py-24 text-white">
            <div class="mx-auto grid max-w-7xl gap-14 px-6 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-300">Demonstração gratuita</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight md:text-4xl">Veja o seu escritório funcionando no Contab Admin</h2>
                    <p class="mt-5 text-lg leading-relaxed text-sky-100/90">Em uma conversa rápida mostramos o sistema com exemplos da rotina do seu escritório e tiramos todas as dúvidas. Sem compromisso.</p>
                    <ul class="mt-8 space-y-4 text-sky-50">
                        @foreach (['Demonstração ao vivo, com os seus casos', 'Ajuda para montar o catálogo de documentos', 'Implantação acompanhada pela nossa equipe'] as $item)
                            <li class="flex items-center gap-3">
                                <svg class="h-6 w-6 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['check'] }}" /></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-2xl bg-white p-8 text-slate-700 shadow-2xl">
                    @if (session('contato_enviado'))
                        <div class="py-10 text-center">
                            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </span>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Recebemos o seu pedido!</h3>
                            <p class="mt-2 text-slate-500">Nossa equipe vai entrar em contato em até 1 dia útil para agendar a demonstração.</p>
                        </div>
                    @else
                        <h3 class="text-xl font-bold text-slate-900">Agende sua demonstração</h3>
                        <p class="mt-1 text-sm text-slate-500">Preencha e retornamos em até 1 dia útil.</p>

                        <form method="POST" action="{{ route('demonstracao') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                            @csrf
                            {{-- anti-spam --}}
                            <input type="text" name="site" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                            @foreach ([['nome', 'Seu nome', 'text', true], ['escritorio', 'Nome do escritório', 'text', true], ['email', 'E-mail', 'email', true], ['telefone', 'Telefone / WhatsApp', 'tel', false]] as [$campo, $rotulo, $tipo, $obrigatorio])
                                <div>
                                    <label for="{{ $campo }}" class="block text-sm font-medium text-slate-700">{{ $rotulo }}{{ $obrigatorio ? ' *' : '' }}</label>
                                    <input id="{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" value="{{ old($campo) }}" @required($obrigatorio)
                                           class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:border-[#1f3a5f] focus:outline-none focus:ring-2 focus:ring-sky-200">
                                    @error($campo) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach

                            <div class="sm:col-span-2">
                                <label for="quantidade_empresas" class="block text-sm font-medium text-slate-700">Quantas empresas o escritório atende?</label>
                                <select id="quantidade_empresas" name="quantidade_empresas" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 focus:border-[#1f3a5f] focus:outline-none focus:ring-2 focus:ring-sky-200">
                                    <option value="">Selecione</option>
                                    @foreach (\App\Http\Controllers\ContatoComercialController::FAIXAS_EMPRESAS as $valor => $rotulo)
                                        <option value="{{ $valor }}" @selected(old('quantidade_empresas') === $valor)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="mensagem" class="block text-sm font-medium text-slate-700">O que você quer resolver primeiro? (opcional)</label>
                                <textarea id="mensagem" name="mensagem" rows="3" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:border-[#1f3a5f] focus:outline-none focus:ring-2 focus:ring-sky-200">{{ old('mensagem') }}</textarea>
                            </div>

                            <button type="submit" class="sm:col-span-2 rounded-xl bg-[#1f3a5f] px-6 py-3.5 font-semibold text-white shadow-lg hover:bg-[#2a4d7c]">Quero agendar minha demonstração</button>
                            <p class="sm:col-span-2 text-center text-xs text-slate-400">Seus dados são usados apenas para este contato, conforme a LGPD.</p>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    </main>

    {{-- ================================================================ RODAPÉ --}}
    <footer class="bg-[#0b1a30] text-sky-100/70">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-4">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-white/10">
                        <svg class="h-5 w-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    <span class="text-lg font-bold text-white">Contab Admin</span>
                </div>
                <p class="mt-4 max-w-sm text-sm leading-relaxed">O sistema que organiza a rotina mensal do escritório contábil e das empresas que ele atende.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Produto</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="#recursos" class="hover:text-white">Recursos</a></li>
                    <li><a href="#portal" class="hover:text-white">Portal da empresa</a></li>
                    <li><a href="#planos" class="hover:text-white">Planos</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold text-white">Acesso</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('login') }}" class="hover:text-white">Entrar no sistema</a></li>
                    <li><a href="#contato" class="hover:text-white">Agendar demonstração</a></li>
                    <li><a href="#duvidas" class="hover:text-white">Dúvidas frequentes</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="mx-auto max-w-7xl px-6 py-6 text-xs">© {{ now()->year }} Contab Admin. Todos os direitos reservados.</p>
        </div>
    </footer>
</x-layouts.publico>
