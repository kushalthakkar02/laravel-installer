<?php

namespace Froiden\LaravelInstaller\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WelcomeController extends Controller
{
    /**
     * Display the installer welcome page.
     *
     * @return \Illuminate\View\View
     */

    public function welcome()
    {
        return view('vendor.installer.welcome');
    }

    public function adminDetails()
    {
        return view('vendor.installer.admin-details');
    }

    //verification
    public function verifyPurchaseCode(Request $request, Redirector $redirect)
    {
        if (RateLimiter::tooManyAttempts('verify-license:' . $request->ip(), 5)) {
            return redirect()->route('LaravelInstaller::admin-details')->with('message', __('Rate Limit reached, please try after sometime.'));
        }

        RateLimiter::hit('verify-license:' . $request->ip(), 3600);

        $rules = config('installer.welcome.rules');

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('LaravelInstaller::admin-details')
                ->withInput()
                ->withErrors($validator);
        }

        session(['email' => $request->email]);
        session(['installed' => true]);
        session(['password' => $request->password]);

        // update .env file and server/.env file with SIGNALING_TOKEN
        $token = Str::random(32);
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, "SIGNALING_TOKEN=")) {
            $envContent = preg_replace("/SIGNALING_TOKEN=.*/m", "SIGNALING_TOKEN=$token", $envContent);
        } else {
            $envContent .= "\nSIGNALING_TOKEN=$token\n";
        }

        file_put_contents($envPath, $envContent);

        $serverEnvPath = base_path('/server/.env');
        $serverEnvContent = file_get_contents($serverEnvPath);

        $domainLine = "DOMAIN=" . $request->domainName;
        $tokenLine = "SIGNALING_TOKEN=" . $token;

        if (str_contains($serverEnvContent, "DOMAIN=")) {
            $serverEnvContent = preg_replace("/^DOMAIN=.*/m", $domainLine, $serverEnvContent);
        } else {
            $serverEnvContent .= "\n$domainLine";
        }

        if (str_contains($serverEnvContent, "SIGNALING_TOKEN=")) {
            $serverEnvContent = preg_replace("/^SIGNALING_TOKEN=.*/m", $tokenLine, $serverEnvContent);
        } else {
            $serverEnvContent .= "\n$tokenLine";
        }

        $serverEnvContent = trim($serverEnvContent) . "\n";

        file_put_contents($serverEnvPath, $serverEnvContent);

        return $redirect->route('LaravelInstaller::environment');
    }

}
