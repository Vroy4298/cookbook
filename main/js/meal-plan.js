// Main event listener that runs when the DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {
  // Mobile menu toggle functionality
  document.getElementById("mobile-menu-button")?.addEventListener("click", () => {
    const mobileMenu = document.getElementById("mobile-menu")
    mobileMenu?.classList.toggle("hidden")
  })

  // User menu toggle functionality
  document.getElementById("user-menu-button")?.addEventListener("click", () => {
    const userMenu = document.getElementById("user-menu")
    userMenu?.classList.toggle("hidden")
  })

  // Create meal plan modal functionality
  const createPlanBtn = document.getElementById("create-plan-btn")
  const createPlanModal = document.getElementById("create-plan-modal")
  const closeCreateModal = document.getElementById("close-create-modal")
  const cancelCreatePlan = document.getElementById("cancel-create-plan")

  // Empty state create plan button functionality
  document.getElementById("empty-create-plan-btn")?.addEventListener("click", () => {
    createPlanModal?.classList.remove("hidden")
  })

  createPlanBtn?.addEventListener("click", () => {
    createPlanModal?.classList.remove("hidden")
  })

  closeCreateModal?.addEventListener("click", () => {
    createPlanModal?.classList.add("hidden")
  })

  cancelCreatePlan?.addEventListener("click", () => {
    createPlanModal?.classList.add("hidden")
  })

  // Add meal modal functionality
  const addMealModal = document.getElementById("add-meal-modal")
  const closeAddMealModal = document.getElementById("close-add-meal-modal")
  const cancelAddMeal = document.getElementById("cancel-add-meal")
  const addMealForm = document.getElementById("add-meal-form")
  const selectedRecipeId = document.getElementById("selected-recipe-id")
  const selectedSlotId = document.getElementById("selected-slot-id")
  const confirmAddMeal = document.getElementById("confirm-add-meal")

  // Add meal buttons
  document.querySelectorAll(".add-meal-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const slotId = this.getAttribute("data-slot-id")
      if (slotId) {
        selectedSlotId.value = slotId
        addMealModal?.classList.remove("hidden")
      }
    })
  })

  closeAddMealModal?.addEventListener("click", () => {
    addMealModal?.classList.add("hidden")
  })

  cancelAddMeal?.addEventListener("click", () => {
    addMealModal?.classList.add("hidden")
  })

  // Recipe selection
  const recipeItems = document.querySelectorAll(".meal-item[data-recipe-id]")

  recipeItems.forEach((item) => {
    item.addEventListener("click", function () {
      recipeItems.forEach((i) => i.classList.remove("border-yellow-300", "bg-yellow-50"))

      this.classList.add("border-yellow-300", "bg-yellow-50")
      selectedRecipeId.value = this.getAttribute("data-recipe-id")
      confirmAddMeal.disabled = false
    })
  })

  // Recipe search
  const recipeSearch = document.getElementById("recipe-search")
  recipeSearch?.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase()
    recipeItems.forEach((item) => {
      const recipeName = item.querySelector(".font-medium")?.textContent.toLowerCase() || ""
      item.style.display = recipeName.includes(searchTerm) ? "flex" : "none"
    })
  })

  // Remove meal
  document.querySelectorAll(".remove-meal-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.stopPropagation()
      const slotId = this.getAttribute("data-slot-id")
      if (confirm("Are you sure you want to remove this meal from your plan?")) {
        removeMeal(slotId)
      }
    })
  })

  function removeMeal(slotId) {
    fetch(window.location.href, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `remove-meal=true&slot_id=${slotId}`,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          location.reload()
        } else {
          showToast(data.message || "Error removing meal from plan", "error")
        }
      })
      .catch((err) => {
        console.error("Error:", err)
        showToast("An error occurred while removing the meal", "error")
      })
  }

  function showToast(message, type = "success") {
    const toast = document.getElementById("toast")
    toast.textContent = message
    toast.className = `toast ${type}`
    setTimeout(() => toast.classList.add("show"), 100)
    setTimeout(() => {
      toast.classList.remove("show")
      setTimeout(() => (toast.className = "toast"), 300)
    }, 3000)
  }

  // Close modals on outside click
  window.addEventListener("click", (e) => {
    if (e.target === createPlanModal) createPlanModal.classList.add("hidden")
    if (e.target === addMealModal) addMealModal.classList.add("hidden")
  })

  // Date range setup
  const startDateInput = document.getElementById("start-date")
  const endDateInput = document.getElementById("end-date")
  const planNameInput = document.getElementById("plan-name")

  if (startDateInput && endDateInput && planNameInput) {
    const today = new Date()
    const nextWeek = new Date()
    nextWeek.setDate(today.getDate() + 6)

    startDateInput.valueAsDate = today
    endDateInput.valueAsDate = nextWeek

    const startFormatted = today.toLocaleDateString("en-US", { month: "short", day: "numeric" })
    const endFormatted = nextWeek.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
    planNameInput.value = `Weekly Plan (${startFormatted} - ${endFormatted})`

    startDateInput.addEventListener("change", updatePlanName)
    endDateInput.addEventListener("change", updatePlanName)

    function updatePlanName() {
      const start = new Date(startDateInput.value)
      const end = new Date(endDateInput.value)
      const startFormat = start.toLocaleDateString("en-US", { month: "short", day: "numeric" })
      const endFormat = end.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
      planNameInput.value = `Weekly Plan (${startFormat} - ${endFormat})`
    }
  }

  // Add meal form
  addMealForm?.addEventListener("submit", function (e) {
    e.preventDefault()
    const formData = new FormData(this)
    const params = new URLSearchParams()
    for (const [key, value] of formData) params.append(key, value)

    fetch(window.location.href, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: params,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          addMealModal?.classList.add("hidden")
          showToast("Meal added to plan successfully!")
          location.reload()
        } else {
          showToast(data.message || "Error adding meal to plan", "error")
        }
      })
      .catch((err) => {
        console.error("Error:", err)
        showToast("An error occurred while adding the meal", "error")
      })
  })

  // Delete meal plan
  document.querySelectorAll(".delete-plan-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.stopPropagation()
      const planId = this.getAttribute("data-plan-id")
      if (confirm("Are you sure you want to delete this meal plan? This action cannot be undone.")) {
        deleteMealPlan(planId)
      }
    })
  })

  function deleteMealPlan(planId) {
    fetch(window.location.href, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `delete_plan=true&plan_id=${planId}`,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          showToast("Meal plan deleted successfully", "success")
          setTimeout(() => location.reload(), 1000)
        } else {
          showToast(data.message || "Error deleting meal plan", "error")
        }
      })
      .catch((err) => {
        console.error("Error:", err)
        showToast("An error occurred while deleting the meal plan", "error")
      })
  }
})
