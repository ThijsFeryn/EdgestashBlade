<?php

namespace ThijsFeryn\EdgestashBlade\Provider;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class EdgestashBladeServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        Blade::directive('edgestashIfDetected', function (string $expression) {
            if(!Str::contains($expression, ',')) {
                return "<?php echo $expression; ?>";
            }

            $expression = $this->parseMultipleArgs($expression);
            $value = $expression->get(0);

            if(!$this->isEdgestash()) {
                return "<?php echo $value; ?>";
            }

            $name = $expression->get(1);
            $url = trim($expression->get(2),'\'');

            if(null !== $url && \strlen($url) > 0) {
                $urls = $this->app->request->attributes->get('edgestash-json-urls',[]);
                $urls[] = $url;
                $this->app->request->attributes->set('edgestash-json-urls',$urls);
            }

            return "<?php echo '@{{' . $name . '}}' ; ?>";
        });

        Blade::directive('edgestash', function (string $expression) {
            if(!Str::contains($expression, ',')) {
                return "<?php echo '@{{' . $expression . '}}' ; ?>";
            }

            $expression = $this->parseMultipleArgs($expression);
            $name = $expression->get(0);
            $url = trim($expression->get(1),'\'');

            if(null !== $url && \strlen($url) > 0) {
                $urls = $this->app->request->attributes->get('edgestash-json-urls',[]);
                $urls[] = $url;
                $this->app->request->attributes->set('edgestash-json-urls',$urls);
            }

            return "<?php echo '@{{' . $name . '}}' ; ?>";
        });

        Blade::if('isEdgestash', function () {
            return $this->isEdgestash();
        });
    }
    /**
     * @return bool
     */
    public function isEdgestash(): bool
    {
        return (bool)$this->app->request->attributes->get('edgestash');
    }
    /**
     * @param string $expression
     * @return \Illuminate\Support\Collection
     */
    private function parseMultipleArgs(string $expression): object
    {
        return collect(explode(',', $expression))->map(function ($item) {
            return trim($item);
        });
    }
}