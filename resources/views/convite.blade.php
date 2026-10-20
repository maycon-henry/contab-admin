<x-layouts.publico titulo="Aceitar convite">
    <div class="flex min-h-screen items-center justify-center px-6">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-xl font-semibold text-[#1f3a5f]">Criar sua senha</h1>

            @if (! $user)
                <p class="mt-4 text-slate-600">Este convite é inválido ou expirou. Peça um novo convite ao escritório.</p>
                <a href="{{ route('landing') }}" class="mt-6 inline-block text-sm font-semibold text-[#1f3a5f]">Voltar</a>
            @else
                <p class="mt-2 text-sm text-slate-600">Olá, {{ $user->name }}! Defina a senha de acesso para <strong>{{ $user->email }}</strong>.</p>

                <form method="POST" action="{{ route('convite.aceitar', $token) }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium" for="password">Senha</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-[#1f3a5f] focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">Mínimo de 8 caracteres, com letras e números.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="password_confirmation">Confirme a senha</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-[#1f3a5f] focus:outline-none">
                    </div>

                    @error('password')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="w-full rounded-md bg-[#1f3a5f] px-4 py-2 font-semibold text-white hover:bg-[#2a4d7c]">Criar senha e entrar</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.publico>
