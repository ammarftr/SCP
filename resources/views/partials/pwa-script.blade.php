<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register("{{ asset('sw.js') }}", { updateViaCache: 'none' }).catch(function () {});
        });
    }
</script>
