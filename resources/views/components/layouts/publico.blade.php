@props([
    'titulo' => config('app.name'),
    'descricao' => 'Sistema para escritórios de contabilidade: documentos do mês, notas fiscais por XML, admissões e cobrança automática das empresas em um só lugar.',
])
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descricao }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:locale" content="pt_BR">
    <meta name="theme-color" content="#1f3a5f">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231f3a5f'/%3E%3Cpath d='M9 17l4 4 10-10' stroke='%2338bdf8' stroke-width='3.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen overflow-x-hidden bg-white text-slate-700 antialiased">
    {{ $slot }}
</body>
</html>
