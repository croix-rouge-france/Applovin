        
        <style>
        /* Style pour le footer collé en bas */
html, body {
    height: 100%;
}

body {
    display: flex;
    flex-direction: column;
}

main {
    flex: 1 0 auto;
}

.footer {
    flex-shrink: 0;
    background-color: #1a1a1a;
}

/* Style épuré */
.footer a {
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer a:hover {
    color: #fff !important;
}

.footer .nav-link {
    padding: 0.25rem 0;
}

.footer hr {
    opacity: 0.1;
}

.footer-brand p {
    font-size: 0.9rem;
}
        </style>
        </main>

        <!-- Pied de page - Style épuré collé en bas -->
        <footer class="footer mt-auto py-4 bg-dark text-white">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="footer-brand">
                            <h5 class="mb-3"><?= APP_NAME ?></h5>
                            <p class="text-muted">Votre plateforme d'investissement sécurisée.</p>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <h5 class="mb-3">Navigation</h5>
                        <ul class="nav flex-column">
                            <li class="nav-item mb-2">
                                <a href="dashboard.php" class="nav-link p-0 text-muted">Tableau de bord</a>
                            </li>
                            <li class="nav-item mb-2">
                                <a href="invest.php" class="nav-link p-0 text-muted">Investissements</a>
                            </li>
                            <li class="nav-item mb-2">
                                <a href="withdraw.php" class="nav-link p-0 text-muted">Retraits</a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="col-lg-4">
                        <h5 class="mb-3">Contact</h5>
                        <ul class="nav flex-column">
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2"></i>
                                <span class="text-muted">support@<?= strtolower(APP_NAME) ?>.com</span>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2"></i>
                                <span class="text-muted">+92 98 97 56 43 25</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <hr class="mt-4 mb-4" style="border-color: rgba(255,255,255,0.1);">
                
                <div class="text-center pt-2">
                    <p class="mb-0 text-muted small">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?>. Tous droits réservés.
                    </p>
                </div>
            </div>
        </footer>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js"></script>
    </body>
</html>