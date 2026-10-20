<?php

namespace Bps\Sso\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class SsoButton extends Component
{
    public string $url;
    public string $text;
    public string $class;
    public bool $icon;
    public string $driver;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $url = null,
        ?string $driver = null,
        ?string $text = null,
        string $class = '',
        bool $icon = true
    ) {
        $defaultDriver = config('sso-bps.default', 'pusat');
        $this->driver = $driver ?? $defaultDriver;

        if ($text !== null) {
            $this->text = $text;
        } else {
            $this->text = ($this->driver === 'majapahit') ? 'Masuk dengan SSO Majapahit' : 'Masuk dengan SSO BPS';
        }

        if ($url !== null) {
            $this->url = $url;
        } else {
            if ($this->driver !== $defaultDriver) {
                $driverRoute = config('sso-bps.routes.names.driver_login', 'bps-sso.driver.login');
                $this->url = Route::has($driverRoute)
                    ? route($driverRoute, ['driver' => $this->driver])
                    : url("/sso/{$this->driver}/login");
            } else {
                $loginRoute = config('sso-bps.routes.names.login', 'bps-sso.login');
                $this->url = Route::has($loginRoute)
                    ? route($loginRoute)
                    : url('/sso/login');
            }
        }

        $this->class = $class;
        $this->icon = $icon;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('bps-sso::components.sso-button');
    }
}
