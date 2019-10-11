<?php
namespace ThijsFeryn\EdgestashBlade\Middleware;

class EdgestashBladeMiddleware
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, \Closure $next)
    {
        if ($request->headers->has('Surrogate-Capability') &&
            false !== strpos(
                $request->headers->get('Surrogate-Capability'),
                'edgestash="EDGESTASH/2.1"'
            )
        ) {
            $request->attributes->set('edgestash',true);
        } else {
            $request->attributes->set('edgestash',false);
        }

        $response =  $next($request);

        if($request->attributes->has('edgestash') && $request->attributes->get('edgestash') !== false) {
            $response->headers->set('Surrogate-Control','edgestash="EDGESTASH/2.1"',false);

            if($request->attributes->has('edgestash-json-urls')
                && count($request->attributes->get('edgestash-json-urls')) > 0) {
                $urls = array_unique($request->attributes->get('edgestash-json-urls'));
                foreach ($urls as $url) {
                    $response->headers->set('Link','<'.$url.'>; rel=edgestash',false);
                }
            }
        }

        return $response;
    }
}
