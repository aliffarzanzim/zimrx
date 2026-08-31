function initHoDietDropdown() {
  document.querySelectorAll('.ho-diet-dropdown-wrapper').forEach((wrapper) => {
    if (wrapper.dataset.hoDietReady === '1') {
      return;
    }
    wrapper.dataset.hoDietReady = '1';

    const dietInput = wrapper.querySelector('.ho-diet-input');
    const dietDropdownMenu = wrapper.querySelector('.ho-diet-dropdown-menu');
    const dietCustomInput = wrapper.querySelector('.ho-diet-custom-input');
    const dietOptions = Array.from(wrapper.querySelectorAll('.ho-diet-option'));

    if (!dietInput || !dietDropdownMenu || !dietCustomInput || !dietOptions.length) {
      return;
    }

    let isDropdownOpen = false;
    let hasSelection = false;

    function visibleOptions() {
      return dietOptions.filter((option) => option.style.display !== 'none');
    }

    function filterAndShowDropdown(searchValue) {
      const normalizedSearch = String(searchValue || '').toLowerCase();
      let hasVisibleOptions = false;

      dietOptions.forEach((option) => {
        const optionValue = String(option.dataset.value || option.textContent || '').toLowerCase();
        const isMatch = optionValue.includes(normalizedSearch);
        option.style.display = isMatch ? 'block' : 'none';
        option.classList.remove('active');
        hasVisibleOptions = hasVisibleOptions || isMatch;
      });

      dietDropdownMenu.style.display = hasVisibleOptions ? 'block' : 'none';
      isDropdownOpen = hasVisibleOptions;

      const firstVisible = visibleOptions()[0];
      if (firstVisible) {
        firstVisible.classList.add('active');
      }
    }

    function hideDropdown() {
      dietDropdownMenu.style.display = 'none';
      isDropdownOpen = false;
    }

    function chooseOption(option) {
      const value = option.dataset.value || option.textContent.trim();

      if (value === 'Custom') {
        dietInput.style.display = 'none';
        dietInput.value = '';
        hideDropdown();
        dietCustomInput.style.display = 'block';
        dietCustomInput.value = '';
        dietCustomInput.focus();
        hasSelection = true;
        return;
      }

      dietInput.value = value;
      dietInput.style.display = 'block';
      dietCustomInput.style.display = 'none';
      hideDropdown();
      hasSelection = true;
    }

    dietInput.addEventListener('focus', () => {
      if (!hasSelection) {
        filterAndShowDropdown('');
      }
    });

    dietInput.addEventListener('input', (event) => {
      const searchValue = event.target.value.toLowerCase();
      if (searchValue.length > 0) {
        hasSelection = false;
      }
      filterAndShowDropdown(searchValue);
    });

    dietOptions.forEach((option) => {
      option.addEventListener('mousedown', (event) => {
        event.preventDefault();
        chooseOption(option);
      });
    });

    dietInput.addEventListener('keydown', (event) => {
      const options = visibleOptions();
      const activeOption = dietDropdownMenu.querySelector('.ho-diet-option.active');
      let activeIndex = activeOption ? options.indexOf(activeOption) : -1;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!isDropdownOpen) {
          filterAndShowDropdown('');
          return;
        }
        if (!options.length) return;
        activeOption?.classList.remove('active');
        activeIndex = (activeIndex + 1) % options.length;
        options[activeIndex].classList.add('active');
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!options.length) return;
        activeOption?.classList.remove('active');
        activeIndex = activeIndex <= 0 ? options.length - 1 : activeIndex - 1;
        options[activeIndex].classList.add('active');
      } else if (event.key === 'Enter') {
        const option = dietDropdownMenu.querySelector('.ho-diet-option.active');
        if (option && isDropdownOpen) {
          event.preventDefault();
          chooseOption(option);
        }
      } else if (event.key === 'Escape') {
        hideDropdown();
      }
    });
  });
}

document.addEventListener('click', (event) => {
  document.querySelectorAll('.ho-diet-dropdown-wrapper').forEach((wrapper) => {
    if (!wrapper.contains(event.target)) {
      const menu = wrapper.querySelector('.ho-diet-dropdown-menu');
      if (menu) {
        menu.style.display = 'none';
      }
    }
  });
});
