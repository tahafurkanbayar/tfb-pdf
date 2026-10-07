<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;

final class HomeController extends Controller
{
    /**
     * "/" → tercih edilen dile yönlendir (cookie → tarayıcı dili → tr).
     */
    public function root(Request $request): Response
    {
        return Response::redirect($this->url()->page('/', [], (string) $request->attribute('locale')));
    }

    public function index(Request $request): Response
    {
        return $this->view('pages/home');
    }

    public function about(Request $request): Response
    {
        return $this->view('pages/about');
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy');
    }
}
