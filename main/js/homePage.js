document.addEventListener("DOMContentLoaded", () => {
    // Mobile menu toggle functionality with improved animation
    const mobileMenuButton = document.getElementById("mobile-menu-button")
    const mobileMenu = document.getElementById("mobile-menu")
    const menuIcon = mobileMenuButton?.querySelector("i")
  
    if (mobileMenuButton && mobileMenu) {
      // Add transition class to mobile menu for smooth animation
      mobileMenu.classList.add("transition-all", "duration-300", "ease-in-out", "max-h-0", "overflow-hidden")
  
      mobileMenuButton.addEventListener("click", () => {
        // Toggle between menu open and closed states
        const isOpen = !mobileMenu.classList.contains("hidden")
  
        if (isOpen) {
          // Close menu with animation
          mobileMenu.style.maxHeight = "0px"
          setTimeout(() => {
            mobileMenu.classList.add("hidden")
            if (menuIcon) {
              menuIcon.classList.remove("fa-times")
              menuIcon.classList.add("fa-bars")
            }
          }, 300)
        } else {
          // Open menu with animation
          mobileMenu.classList.remove("hidden")
          mobileMenu.style.maxHeight = mobileMenu.scrollHeight + "px"
          if (menuIcon) {
            menuIcon.classList.remove("fa-bars")
            menuIcon.classList.add("fa-times")
          }
        }
      })
  
      // Close mobile menu when clicking on a link
      const mobileMenuLinks = mobileMenu.querySelectorAll("a")
      mobileMenuLinks.forEach((link) => {
        link.addEventListener("click", () => {
          mobileMenu.style.maxHeight = "0px"
          setTimeout(() => {
            mobileMenu.classList.add("hidden")
            if (menuIcon) {
              menuIcon.classList.remove("fa-times")
              menuIcon.classList.add("fa-bars")
            }
          }, 300)
        })
      })
    }
  
    // Carousel functionality
    const carousel = document.querySelector(".carousel")
    const carouselItems = document.querySelectorAll(".carousel-item")
    const prevBtn = document.getElementById("prev-btn")
    const nextBtn = document.getElementById("next-btn")
    const dots = document.querySelectorAll(".carousel-dot")
  
    if (carousel && carouselItems.length > 0) {
      let currentIndex = 0
      const itemWidth = carousel.clientWidth
  
      // Update carousel position and dot indicators
      function updateCarousel() {
        carousel.scrollLeft = currentIndex * itemWidth
  
        dots.forEach((dot, index) => {
          if (index === currentIndex) {
            dot.classList.add("bg-yellow-300")
            dot.classList.remove("bg-black")
          } else {
            dot.classList.remove("bg-yellow-300")
            dot.classList.add("bg-black")
          }
        })
      }
  
      // Previous button click handler
      if (prevBtn) {
        prevBtn.addEventListener("click", () => {
          currentIndex = currentIndex > 0 ? currentIndex - 1 : carouselItems.length - 1
          updateCarousel()
        })
      }
  
      // Next button click handler
      if (nextBtn) {
        nextBtn.addEventListener("click", () => {
          currentIndex = currentIndex < carouselItems.length - 1 ? currentIndex + 1 : 0
          updateCarousel()
        })
      }
  
      // Dot click handlers
      dots.forEach((dot, index) => {
        dot.addEventListener("click", () => {
          currentIndex = index
          updateCarousel()
        })
      })
  
      // Auto-advance carousel every 5 seconds
      setInterval(() => {
        currentIndex = currentIndex < carouselItems.length - 1 ? currentIndex + 1 : 0
        updateCarousel()
      }, 5000)
    }
  
    // Update user icon color if user is logged in
    const userData = localStorage.getItem("cookbook_user")
    if (userData) {
      const userIcon = document.querySelector(".fa-user-circle")
      if (userIcon) {
        userIcon.classList.add("text-yellow-300")
        userIcon.classList.remove("text-text")
      }
    }
  })