{{--
 | The loader of the tenant's Google tag (Noerd\Cms\Services\WebsiteTrackingService).
 | Nothing loads here: the cookie banner calls loadWebsiteGoogleTag() once the visitor
 | agreed to analytics cookies — on the click and on every later full page load. The
 | guard keeps it to one load per page. The service only hands out an ID together with
 | the banner and on a host that shares no session with the backend; the <noscript>
 | fallback of Tag Manager is left out on purpose, it would load without consent.
--}}
@if ($tracking['showCookieBanner'] ?? false)
    {!! \Devrabiul\CookieConsent\Facades\CookieConsent::styles() !!}
@endif
@if ($tracking['googleTagId'] ?? null)
    <script data-navigate-once>
        window.loadWebsiteGoogleTag = function () {
            if (window.__websiteGoogleTagLoaded) {
                return;
            }
            window.__websiteGoogleTagLoaded = true;

            const id = {{ \Illuminate\Support\Js::from($tracking['googleTagId']) }};
            const script = document.createElement('script');
            script.async = true;
            window.dataLayer = window.dataLayer || [];

            if (id.startsWith('GTM-')) {
                window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
                script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
            } else {
                window.gtag = function () { window.dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', id);
                script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
            }

            document.head.appendChild(script);
        };
    </script>
@endif
