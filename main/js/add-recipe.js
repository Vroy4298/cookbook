// Main event listener that runs when the DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {
  // Mobile menu toggle
  const mobileMenuBtn = document.getElementById("mobile-menu-button");
  const mobileMenu = document.getElementById("mobile-menu");
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener("click", () => {
      mobileMenu.classList.toggle("hidden");
    });
  }

  // User menu toggle
  const userMenuBtn = document.getElementById("user-menu-button");
  const userMenu = document.getElementById("user-menu");
  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener("click", () => {
      userMenu.classList.toggle("hidden");
    });
  }

  // Get elements
  const addRecipeBtn = document.getElementById("add-recipe-btn");
  const emptyAddRecipeBtn = document.getElementById("empty-add-recipe-btn");
  const addRecipeModal = document.getElementById("add-recipe-modal");
  const closeAddModal = document.getElementById("close-add-modal");
  const cancelAddRecipe = document.getElementById("cancel-add-recipe");
  const uploadTrigger = document.getElementById("upload-trigger");
  const recipeImage = document.getElementById("recipe-image");
  const ingredientsContainer = document.getElementById("ingredients-container");
  const addIngredientBtn = document.getElementById("add-ingredient");
  const instructionsContainer = document.getElementById("instructions-container");
  const addInstructionBtn = document.getElementById("add-instruction");

  // Show modal
  function showAddRecipeModal() {
    if (addRecipeModal) {
      addRecipeModal.classList.remove("hidden");
    }
    const form = document.getElementById("add-recipe-form");
    if (form) form.reset();
    resetDynamicFields();
  }

  // Hide modal
  function hideAddRecipeModal() {
    if (addRecipeModal) {
      addRecipeModal.classList.add("hidden");
    }
  }

  // Reset dynamic fields
  function resetDynamicFields() {
    if (ingredientsContainer) {
      ingredientsContainer.innerHTML = `
        <div class="flex items-center gap-2 mb-2">
          <input type="text" name="ingredient[]" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 2 cups flour" required>
          <button type="button" class="remove-ingredient bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
    }

    if (instructionsContainer) {
      instructionsContainer.innerHTML = `
        <div class="flex items-center gap-2 mb-2">
          <span class="bg-yellow-300 text-black w-6 h-6 rounded-full flex items-center justify-center font-medium">1</span>
          <textarea name="instruction[]" rows="2" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. Preheat oven to 350°F" required></textarea>
          <button type="button" class="remove-instruction bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
    }
  }

  // Modal open buttons
  if (addRecipeBtn) addRecipeBtn.addEventListener("click", showAddRecipeModal);
  if (emptyAddRecipeBtn) emptyAddRecipeBtn.addEventListener("click", showAddRecipeModal);

  // Modal close buttons
  if (closeAddModal) closeAddModal.addEventListener("click", hideAddRecipeModal);
  if (cancelAddRecipe) cancelAddRecipe.addEventListener("click", hideAddRecipeModal);

  // Image upload preview
  if (uploadTrigger && recipeImage) {
    uploadTrigger.addEventListener("click", () => {
      recipeImage.click();
    });

    recipeImage.addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          const preview = document.createElement("img");
          preview.src = e.target.result;
          preview.classList.add("w-full", "h-48", "object-cover", "rounded-lg", "mt-2");

          const existingPreview = uploadTrigger.parentElement.querySelector("img");
          if (existingPreview) existingPreview.remove();

          uploadTrigger.parentElement.appendChild(preview);
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Add new ingredient
  if (addIngredientBtn && ingredientsContainer) {
    addIngredientBtn.addEventListener("click", () => {
      const ingredientDiv = document.createElement("div");
      ingredientDiv.className = "flex items-center gap-2 mb-2";
      ingredientDiv.innerHTML = `
        <input type="text" name="ingredient[]" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 2 cups flour" required>
        <button type="button" class="remove-ingredient bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200">
          <i class="fas fa-times"></i>
        </button>
      `;
      ingredientsContainer.appendChild(ingredientDiv);
    });
  }

  // Add new instruction
  if (addInstructionBtn && instructionsContainer) {
    addInstructionBtn.addEventListener("click", () => {
      const count = instructionsContainer.children.length + 1;
      const instructionDiv = document.createElement("div");
      instructionDiv.className = "flex items-center gap-2 mb-2";
      instructionDiv.innerHTML = `
        <span class="bg-yellow-300 text-black w-6 h-6 rounded-full flex items-center justify-center font-medium">${count}</span>
        <textarea name="instruction[]" rows="2" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. Step ${count}" required></textarea>
        <button type="button" class="remove-instruction bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200">
          <i class="fas fa-times"></i>
        </button>
      `;
      instructionsContainer.appendChild(instructionDiv);
    });
  }

  // Remove ingredient handler (event delegation)
  if (ingredientsContainer) {
    ingredientsContainer.addEventListener("click", (e) => {
      if (e.target.closest(".remove-ingredient")) {
        const item = e.target.closest(".flex");
        if (item && ingredientsContainer.children.length > 1) {
          item.remove();
        }
      }
    });
  }

  // Remove instruction handler (event delegation)
  if (instructionsContainer) {
    instructionsContainer.addEventListener("click", (e) => {
      if (e.target.closest(".remove-instruction")) {
        const item = e.target.closest(".flex");
        if (item && instructionsContainer.children.length > 1) {
          item.remove();

          // Re-number instructions
          const steps = instructionsContainer.querySelectorAll("span");
          steps.forEach((step, index) => {
            step.textContent = index + 1;
          });
        }
      }
    });
  }
});
