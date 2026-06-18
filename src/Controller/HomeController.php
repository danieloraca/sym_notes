<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return new Response(<<<'HTML'
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sym Notes</title>
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #172033;
                background: #f7f3ea;
            }

            * {
                box-sizing: border-box;
            }

            body {
                min-height: 100vh;
                margin: 0;
                display: grid;
                place-items: center;
                padding: 32px;
            }

            main {
                width: min(100%, 720px);
                border: 1px solid #d8d2c7;
                border-radius: 8px;
                background: #fffaf1;
                padding: clamp(32px, 8vw, 72px);
                box-shadow: 0 24px 80px rgb(23 32 51 / 0.12);
            }

            p:first-child {
                margin: 0 0 16px;
                color: #5e6b7d;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: clamp(2.5rem, 8vw, 5rem);
                line-height: 0.95;
            }

            p:last-child {
                max-width: 560px;
                margin: 24px 0 0;
                color: #465365;
                font-size: 1.1rem;
                line-height: 1.7;
            }
        </style>
    </head>
    <body>
        <main>
            <p>Symfony is running</p>
            <h1>Sym Notes</h1>
            <p>A small first page is wired up and ready for the rest of the app.</p>
        </main>
    </body>
</html>
HTML);
    }
}
