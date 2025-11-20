<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Control - Andon Board</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/config.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Oswald:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="flex flex-col p-4 gap-4 select-none">

    <!-- HEADER -->
    <header class="flex-none flex justify-between items-center bg-[#161f32] px-6 py-4 rounded-xl border-l-8 border-info shadow-lg">
        <div class="flex flex-col justify-center">
            <h1 class="text-tv-2xl font-bold text-white tracking-wider uppercase drop-shadow-md leading-none">
                DELIVERY CONTROL
            </h1>
            <div class="flex items-center gap-4 mt-2">
                <span class="bg-blue-900/50 text-blue-300 px-3 py-0.5 rounded text-tv-sm font-mono tracking-widest border border-blue-800">
                    CLOSED LOOP SUPPLY CHAIN
                </span>
                <span id="loading-indicator" class="text-yellow-500 text-tv-sm animate-pulse hidden">SYNCING...</span>
            </div>
        </div>
        <div class="text-right">
            <div class="text-tv-huge font-mono font-bold text-white leading-none tracking-tighter" id="clock">00:00</div>
            <div class="text-tv-sm text-slate-400 font-mono mt-1 uppercase" id="date-display">---, -- --- ----</div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-h-0 grid grid-cols-12 gap-4 relative">

        <!-- KIRI: DELIVERY SCOREBOARD (4/12) -->
        <section class="col-span-4 flex flex-col h-full">
            <div class="bg-[#161f32] rounded-xl border border-slate-800 shadow-2xl flex flex-col h-full overflow-hidden">
                <div class="flex-none bg-gradient-to-r from-slate-800 to-[#161f32] p-4 border-b border-slate-700">
                    <h2 class="text-tv-lg font-bold text-info flex items-center gap-3">
                        <span class="w-3 h-6 bg-info rounded-sm"></span>
                        DELIVERY PROGRESS
                    </h2>
                </div>

                <div class="flex-1 p-3 flex flex-col justify-between overflow-hidden" id="delivery-container">
                    <!-- Content Injected by JS -->
                    <div class="flex-1 flex items-center justify-center text-slate-600 text-tv-lg animate-pulse">INITIALIZING...</div>
                </div>

                <div class="flex-none bg-slate-900 border-t-4 border-slate-700 p-4 flex items-center justify-between relative overflow-hidden h-32">
                    <div class="z-10 w-full">
                        <div class="text-tv-xs text-slate-400 tracking-widest uppercase mb-1" id="overall-label">TOTAL ACHIEVEMENT</div>
                        <div id="overall-rate" class="text-tv-2xl font-mono font-bold text-white leading-none">0%</div>
                    </div>
                    <div class="absolute right-0 top-0 bottom-0 w-3/5 bg-slate-800 skew-x-12 mr-[-30px] flex items-center justify-center border-l border-slate-700">
                        <div class="w-full h-full bg-slate-700 opacity-30 absolute bottom-0 transition-all duration-1000" id="overall-bar-bg" style="height: 0%"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- KANAN: CAROUSEL (LOCATOR & IN-TRANSIT) (8/12) -->
        <section class="col-span-8 flex flex-col h-full">
            <div class="bg-[#161f32] rounded-xl border border-slate-800 shadow-2xl flex flex-col h-full overflow-hidden">
                <!-- Header Dinamis -->
                <div class="flex-none bg-gradient-to-r from-slate-800 to-[#161f32] p-4 border-b border-slate-700 flex justify-between items-center">
                    <h2 id="right-panel-title" class="text-tv-lg font-bold text-warning flex items-center gap-3">
                        <span class="w-3 h-6 bg-warning rounded-sm"></span>
                        WIP LOCATOR (FG)
                    </h2>
                    <div class="flex items-center gap-4">
                        <div class="text-tv-lg font-mono text-white bg-slate-800 px-4 py-1 rounded border border-slate-600">
                            <span id="page-indicator" class="text-warning">INIT</span>
                        </div>
                    </div>
                </div>

                <!-- Table Container Dinamis -->
                <div id="right-panel-content" class="flex-1 relative px-4 py-2 overflow-hidden flex flex-col transition-opacity duration-500">
                    <table class="w-full text-left border-collapse h-full table-fixed">
                        <thead id="right-table-head">
                            <!-- Header Injected by JS -->
                        </thead>
                        <tbody id="right-table-body">
                            <!-- Rows Injected by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer Legend -->
                <div class="flex-none bg-slate-900 p-2 flex justify-end gap-6 border-t border-slate-700">
                     <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-slate-800 border border-slate-600 rounded"></span>
                        <span class="text-tv-xs text-slate-400">NORMAL</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-danger/50 border border-danger animate-pulse"></span>
                        <span class="text-tv-xs text-danger font-bold">CRITICAL / GAP</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- TOAST NOTIFICATION (Overlay) -->
        <div id="toast-notification" class="absolute bottom-10 right-10 bg-[#1e293b] border-l-8 border-success p-4 rounded-lg shadow-2xl flex items-center gap-4 w-[500px] z-50 toast-hidden">
            <div class="w-24 h-24 bg-slate-800 rounded-md overflow-hidden flex-none border border-slate-600">
                 <!-- Placeholder Image / Real Image -->
                 <img id="toast-img" src="" class="w-full h-full object-cover hidden" onerror="this.style.display='none'">
            </div>
            <div class="flex-1 overflow-hidden">
                <h3 class="text-success font-bold text-tv-base tracking-wide">DELIVERY RECEIVED</h3>
                <div id="toast-sj" class="text-white font-mono text-tv-lg font-bold leading-none mb-1">SJ-000000</div>
                <p id="toast-desc" class="text-slate-400 text-sm truncate">Loading details...</p>
                <p id="toast-time" class="text-slate-500 text-xs mt-1">Just now</p>
            </div>
        </div>

    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>