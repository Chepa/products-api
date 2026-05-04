{% extends "layouts/main.volt" %}

{% block content %}
<link rel="stylesheet" href="/build/panel.css?v={{ assetsVer }}">
<div id="app" class="card" data-api-base="{{ apiBase|e }}">
    <p class="muted">Загрузка…</p>
</div>
<script type="module" src="/build/panel.js?v={{ assetsVer }}"></script>
{% endblock %}
