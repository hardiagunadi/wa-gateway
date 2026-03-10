@if(config('recaptcha.site_key'))
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce

    <div>
        <div wire:ignore>
            <div
                class="g-recaptcha"
                data-sitekey="{{ config('recaptcha.site_key') }}"
                data-callback="filamentRecaptchaCallback"
                data-expired-callback="filamentRecaptchaExpiredCallback"
            ></div>
        </div>

        @if($errors->has('recaptchaToken'))
            <p class="mt-1 text-sm text-red-600">{{ $errors->first('recaptchaToken') }}</p>
        @endif
    </div>

    <script>
        window.filamentRecaptchaCallback = function (token) {
            @this.set('recaptchaToken', token);
        };
        window.filamentRecaptchaExpiredCallback = function () {
            @this.set('recaptchaToken', '');
        };
    </script>
@endif
