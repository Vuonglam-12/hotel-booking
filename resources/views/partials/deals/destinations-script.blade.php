<style>
    .dest-card { position: relative; border-radius: 16px; overflow: hidden; height: 160px; cursor: pointer; transition: all 0.3s ease; }
    .dest-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px -8px rgba(58,74,90,0.25); }
    .dest-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .dest-card:hover img { transform: scale(1.08); }
    .dest-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(10,20,40,0.75) 0%, transparent 60%); }
    .dest-info { position: absolute; bottom: 0; left: 0; right: 0; padding: 12px 14px; }
</style>