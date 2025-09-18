<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
    } **/
public function boot()
{
    parent::boot();

    $this->gate();
}
	protected function gate()
	{
		Gate::define('viewHorizon', function ($user) {
   		 $allowedEmails = explode(',', env('HORIZON_USER', 'admin@example.com'));
    		return in_array($user->email, $allowedEmails);
		});
	}
}
