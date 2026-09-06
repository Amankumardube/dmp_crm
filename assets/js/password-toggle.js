document.querySelectorAll('input[type="password"]').forEach(function (field) {
  if (field.closest('.password-field, .input-wrap')?.querySelector('[data-password-toggle]')) return;
  const wrapper = document.createElement('div');
  wrapper.className = 'password-field';
  field.parentNode.insertBefore(wrapper, field);
  wrapper.appendChild(field);
  const button = document.createElement('button');
  button.className = 'password-toggle';
  button.type = 'button';
  button.setAttribute('aria-label', 'Show password');
  button.setAttribute('title', 'Show password');
  button.innerHTML = '<i class="bi bi-eye"></i>';
  wrapper.appendChild(button);
});

document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
  button.addEventListener('click', function () {
    const field = document.getElementById(button.getAttribute('data-password-toggle'));
    const icon = button.querySelector('i');
    const showing = field.type === 'text';
    field.type = showing ? 'password' : 'text';
    icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
    button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    button.setAttribute('title', showing ? 'Show password' : 'Hide password');
  });
});
