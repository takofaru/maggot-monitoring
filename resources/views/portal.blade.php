<!DOCTYPE html>
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
