<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Каталог товаров</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7fb; color: #1a1a1a; }
        header { background: #111827; color: #fff; padding: 1rem 1.25rem; }
        header h1 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        main { max-width: 1100px; margin: 0 auto; padding: 1.25rem; }
        .card { background: #fff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); margin-bottom: 1rem; }
        label { display: block; font-size: .85rem; color: #4b5563; margin-bottom: .25rem; }
        input, select, textarea, button { font: inherit; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: .45rem .55rem; border: 1px solid #d1d5db; border-radius: 6px; }
        button { cursor: pointer; border: 0; border-radius: 6px; padding: .5rem .85rem; background: #2563eb; color: #fff; }
        button.secondary { background: #6b7280; }
        button.danger { background: #b91c1c; }
        .row { display: grid; gap: .75rem; }
        @media (min-width: 720px) {
            .row.cols-2 { grid-template-columns: 1fr 1fr; }
            .row.cols-3 { grid-template-columns: repeat(3, 1fr); }
        }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: .5rem .4rem; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #374151; font-weight: 600; }
        .muted { color: #6b7280; font-size: .88rem; }
        .err { color: #b91c1c; font-size: .9rem; margin-top: .35rem; }
        h2 { font-size: 1rem; margin: 0 0 .75rem; }
    </style>
</head>
<body>
<header><h1>Product API — панель</h1></header>
<main>
    {% block content %}{% endblock %}
</main>
</body>
</html>
