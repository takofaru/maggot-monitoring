<!DOCTYPE html>
<head>
    @vite('resources/css/app.css')
</head>
<body class="m-0">
    <div class="w-screen h-screen flex justify-center items-center bg-(--bg-colour)">
        <div class="flex flex-col gap-(--size-42) min-w-(--size-304) max-w-(--size-304) items-center">
            <div id="logo">
                Logo
            </div>
            <div id="login" class="flex flex-col gap-(--size-26)">
                <div class="text-(length:--size-42) font-[700] text-(--prime-colour)">Masuk ke Akun</div>
                <div id="login-container" class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) rounded-(--size-16) border-[1.5px] border-(--outline-colour)">
                    <form id="loginForm" method="POST" action="" class="flex flex-col gap-(--size-16)">
                        @csrf
                        <div class="input-text">
                            <label for="email">Email</label>
                            <input
                                id="email"
                                type="text"
                                class="input-field"
                            />
                        </div>
                        <div class="input-text">
                            <label for="pass">Password</label>
                            <input
                                id="pass"
                                type="password"
                            />
                        </div>
                        <button class="input-button" form="loginForm" type="submit">
                            <x-lucide-log-in class="w-(--size-26)" />
                            Masuk ke Akun
                        </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
