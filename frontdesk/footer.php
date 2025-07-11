<?php
// footer.php - Reusable footer component matching dashboard style
?>
      </div> <!-- End of main-content -->
    </div> <!-- End of row -->
  </div> <!-- End of container-fluid -->

  <!-- Footer -->
 <footer class="footer mt-auto py-3 bg-light border-top">
  <div class="container-fluid">
    <div class="d-flex justify-content-center align-items-center flex-column flex-md-row">
      <div class="me-md-5 mb-2 mb-md-0">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> Radiology & Laboratory Information System (RLIS)</span>
      </div>
      <div>
        <span class="text-muted me-3">v2.1.0</span>
        <a href="#" class="text-muted me-3">Privacy Policy</a>
        <a href="#" class="text-muted me-3">Terms of Service</a>
        <a href="#" class="text-muted">Contact Support</a>
      </div>
    </div>
  </div>
</footer>


  <!-- Back to Top Button -->
  <button onclick="topFunction()" id="backToTopBtn" title="Go to top" class="btn btn-primary rounded-circle shadow">
    <i class="fas fa-arrow-up"></i>
  </button>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Custom JS -->
  <script>
    // Back to top button
    let backToTopBtn = document.getElementById("backToTopBtn");
    
    // When the user scrolls down 20px from the top, show the button
    window.onscroll = function() {scrollFunction()};
    
    function scrollFunction() {
      if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        backToTopBtn.style.display = "block";
      } else {
        backToTopBtn.style.display = "none";
      }
    }
    
    // When the user clicks on the button, scroll to the top
    function topFunction() {
      document.body.scrollTop = 0;
      document.documentElement.scrollTop = 0;
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });
  </script>
</body>
</html>