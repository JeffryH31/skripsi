import { ref, onMounted, onUnmounted } from 'vue';

export function useIdleDetector({ onIdle, onActive, idleThresholdSeconds = 300 } = {}) {
    const isIdle = ref(false);
    let cleanup = null;

    const goIdle = () => {
        if (isIdle.value) return;
        isIdle.value = true;
        onIdle?.();
    };

    const goActive = () => {
        if (!isIdle.value) return;
        isIdle.value = false;
        onActive?.();
    };

    onMounted(() => {
        // --- Mode Electron: idle level OS ---
        if (window.electronAPI?.onIdleUpdate) {
            cleanup = window.electronAPI.onIdleUpdate(({ becameIdle, becameActive }) => {
                if (becameIdle) goIdle();
                else if (becameActive) goActive();
            });
            return;
        }

        // --- Fallback browser: deteksi inaktivitas di halaman ---
        let idleTimer = null;

        const resetTimer = () => {
            // aktivitas terdeteksi -> kalau tadinya idle, kembali aktif
            goActive();

            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = setTimeout(goIdle, idleThresholdSeconds * 1000);
        };

        const activityEvents = [
            'mousemove',
            'mousedown',
            'keydown',
            'scroll',
            'touchstart',
            'wheel',
        ];

        // throttle: cukup tandai aktivitas sekali per ~1 detik supaya hemat
        let throttled = false;
        const handleActivity = () => {
            if (throttled) return;
            throttled = true;
            resetTimer();
            setTimeout(() => { throttled = false; }, 1000);
        };

        activityEvents.forEach((evt) =>
            window.addEventListener(evt, handleActivity, { passive: true })
        );

        // tab disembunyikan dianggap idle, kembali ditampilkan dianggap aktif
        const handleVisibility = () => {
            if (document.hidden) goIdle();
            else resetTimer();
        };
        document.addEventListener('visibilitychange', handleVisibility);

        // mulai hitung mundur sejak komponen ter-mount
        resetTimer();

        cleanup = () => {
            if (idleTimer) clearTimeout(idleTimer);
            activityEvents.forEach((evt) =>
                window.removeEventListener(evt, handleActivity)
            );
            document.removeEventListener('visibilitychange', handleVisibility);
        };
    });

    onUnmounted(() => {
        cleanup?.();
    });

    return { isIdle };
}
