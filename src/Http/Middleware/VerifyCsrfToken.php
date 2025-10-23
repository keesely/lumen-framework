<?php
namespace Package\LumenExtra\Middleware;

use Closure;
use Laravel\Lumen\Application;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken {

  use InteractsWithTime;

  /**
   * The encrypter implementation.
   *
   * @var \Illuminate\Contracts\Encryption\Encrypter
   */
  protected $encrypter;

  /**
   * The application instance.
   *
   * @var \Laravel\Lumen\Application
   */
  protected $app;

  /**
   * The URIs that should be excluded from CSRF verification.
   *
   * @var array
   */
  protected $except = [];

  /**
   * Create a new middleware instance.
   *
   * @param \Laravel\Lumen\Application $app
   * @param  \Illuminate\Contracts\Encryption\Encrypter  $encrypter
   * @return void
   */
  public function __construct(Application $app, Encrypter $encrypter) {
    $this->app = $app;
    $this->encrypter = $encrypter;
  }

  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Illuminate\Http\Response
   *
   * @throws \Illuminate\Session\TokenMismatchException
   */
  public function handle($request, Closure $next)
  {
    if (
      $this->isReading($request) || 
      $this->runningUnitTests() ||
      $this->inExceptArray($request) ||
      $this->tokensMatch($request)
    ) {
      return $this->addCookieToResponse($request, $next($request));
    }

    throw new TokenMismatchException('token timeout', 410);
  }

  /**
   * Determine if the session and input CSRF tokens match.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return bool
   */
  protected function tokensMatch($request)
  {
    $token = $this->getTokenFromRequest($request);

    return is_string($request->session()->token()) &&
      is_string($token) &&
      hash_equals($request->session()->token(), $token);
  }

  /**
   * Get the CSRF token from the request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return string
   */
  protected function getTokenFromRequest($request) {
    $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

    if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
      $token = $this->encrypter->decrypt($header, static::serialized());
    }

    return $token;
  }

  /**
   * Add the CSRF token to the response cookies.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Illuminate\Http\Response  $response
   * @return \Illuminate\Http\Response
   */
  protected function addCookieToResponse($request, $response)
  {
    $config = config('session');
    $timeout = $this->availableAt(60 * array_get($config, 'lifetime', 120));
    $response->headers->setCookie(
      new Cookie(
        'XSRF-TOKEN', $request->session()->token(), $timeout,
        array_get($config, 'path', '/'), array_get($config, 'domain'), false, false
      )
    );

    return $response;
  }

  /**
   * Determine if the HTTP request uses a ‘read’ verb.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return bool
   */
  protected function isReading($request)
  {
    return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
  }

  /**
   * Determine if the request has a URI that should pass through CSRF verification.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return bool
   */
  protected function inExceptArray($request) {
    foreach ($this->except as $except) {
      if ($except !== '/') {
        $except = trim($except, '/');
      }

      if ($request->fullUrlIs($except) || $request->is($except)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Determine if the application is running unit tests.
   *
   * @return bool
   */
  protected function runningUnitTests() {
    return $this->app->runningInConsole() && $this->app->runningUnitTests();
  }

  /**
   * Determine if the cookie contents should be serialized.
   *
   * @return bool
   */
  public static function serialized() {
    return EncryptCookies::serialized('XSRF-TOKEN');
  }
}
