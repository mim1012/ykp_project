<script>
    // Production debug flag - disable console.log in production
    window.DEBUG = {{ config('app.debug') ? 'true' : 'false' }};
    window.log = (...args) => window.DEBUG && console.log(...args);
</script>
