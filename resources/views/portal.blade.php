<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun - Maggot Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8F9FA] flex flex-col items-center justify-center min-h-screen font-sans p-4">

    <!-- Container Utama -->
    <div class="w-full max-w-sm flex flex-col items-center">
        <!-- Logo Bulat -->
        <div class="w-24 h-20 bg-gray-300 rounded-full flex items-center justify-center text-gray-700 font-medium text-sm mb-6 shadow-sm">
            LOGO
        </div>

        <!-- Judul -->
        <h1 class="text-3xl font-extrabold text-[#1A382B] mb-8 text-center tracking-tight">
            Masuk ke Akun
        </h1>

        <!-- Card Form Login -->
        <div class="w-full bg-white rounded-2xl border border-gray-300 p-6 shadow-sm">
            <!-- Alert Error jika Login Gagal -->
            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Field Username -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Username</label>
                    <input 
                        type="text" 
                        name="username" 
                        value="{{ old('username') }}" 
                        required 
                        placeholder="Masukkan Username" 
                        class="w-full px-4 py-2.5 bg-[#F8F9FA] border border-gray-300 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1A382B] transition-all"
                    >
                </div>

                <!-- Field Password -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="Masukkan Password" 
                        class="w-full px-4 py-2.5 bg-[#F8F9FA] border border-gray-300 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1A382B] transition-all"
                    >
                </div>

                <!-- Tombol Masuk -->
                <button 
                    type="submit" 
                    class="w-full py-3 bg-[#1A382B] text-white font-bold text-sm rounded-xl hover:bg-[#12271e] transition-colors flex items-center justify-center gap-2 mt-2 shadow-sm"
                >
                    <svg class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <span>Masuk</span>
                </button>
            </form>

            <!-- Link Guest/Siswa -->
            <div class="mt-6 text-center">
                <a href="{{ route('dashboard.index') }}" class="text-xs font-bold text-[#1A382B] hover:underline underline-offset-4"> masuk <a>
            </div>
        </div>
    </div>

</body>
</html>