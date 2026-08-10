<!DOCTYPE html>
<<<<<<< HEAD
<head>
    @vite('resources/css/app.css')
</head>
<body class="m-0">
    <div class="w-screen h-screen flex justify-center items-center">
        <div class="flex flex-col gap-[42px] min-w-[304px]">
            <div id="logo">
                Logo
            </div>
            <div id="login" class="flex flex-col [gap-26px]">
                <div id="login-container" class="flex flex-col gap-[26px]">
                    <form method="POST" action="" class="flex flex-col gap-[16px]">
                        @csrf
                        <div class="input-container">
                            <label for="email">Email</label>
                            <input
                                id="email"
                                type="text"
                                class="input-field"
                            />
                        </div>
                        <div class="input-container">
                            <label for="pass">Password</label>
                            <input
                                id="pass"
                                type="password"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
=======
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun - Maggot Monitoring</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8F7F4] min-h-screen flex items-center justify-center font-sans antialiased p-4">

    <div class="w-full max-w-sm flex flex-col items-center">
        
        <!-- Logo Bulat -->
        <div class="w-24 h-24 bg-[#DCDCDC] rounded-full flex items-center justify-center text-gray-700 font-bold tracking-wider mb-6 shadow-sm">
            LOGO
        </div>

        <h1 class="text-3xl font-extrabold text-[#1A382B] mb-6 text-center">
            Masuk ke Akun
        </h1>

        <div class="w-full bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            
            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="username" class="block text-xs font-bold text-gray-600 mb-1.5">
                        Email atau Username
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="{{ old('username') }}"
                        placeholder="Masukkan Email atau Username" 
                        class="w-full bg-[#F8F7F4] border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1A382B] focus:bg-white transition"
                        required
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-gray-600 mb-1.5">
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Masukkan Password" 
                        class="w-full bg-[#F8F7F4] border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1A382B] focus:bg-white transition"
                        required
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-[#1A382B] hover:bg-[#12281e] text-white rounded-xl py-3 text-sm font-bold flex items-center justify-center gap-2 shadow-sm transition cursor-pointer mt-2"
                >
                    <svg class="w-4 h-4 transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 01-3-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Masuk</span>
                </button>
            </form>

            <div class="mt-5 text-center">
                <span class="text-xs text-gray-400 font-medium">
                    Gunakan kredensial akun terdaftar untuk masuk.
                </span>
            </div>

        </div>

    </div>

</body>
</html>
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
