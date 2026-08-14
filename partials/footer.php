        </main>

        <footer class="border-t border-white/5 px-4 py-4 text-center text-xs text-zinc-600 sm:px-6">
            <?= APP_NAME ?> · Panel interno &copy; <?= date('Y') ?>
        </footer>
    </div>
</div>

<script>
    // Animación de entrada suave de las tarjetas/filas
    window.addEventListener('load', () => {
        if (typeof gsap === 'undefined') return;
        gsap.from('[data-anim]', {
            opacity: 0, y: 18, duration: .6, ease: 'power3.out', stagger: .06,
        });
        // Contadores animados en el dashboard
        document.querySelectorAll('[data-count]').forEach(el => {
            const end = parseFloat(el.dataset.count) || 0;
            const prefix = el.dataset.prefix || '';
            const obj = { v: 0 };
            gsap.to(obj, {
                v: end, duration: 1.4, ease: 'power2.out',
                onUpdate() {
                    el.textContent = prefix + Math.round(obj.v).toLocaleString('es-CO');
                },
            });
        });
    });
</script>
</body>
</html>
