@php
    $gaMeasurementId = config('services.google.analytics_id');
@endphp
@if(!empty($gaMeasurementId))
    <!-- Google Analytics 4 (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($gaMeasurementId));
    </script>
@endif
