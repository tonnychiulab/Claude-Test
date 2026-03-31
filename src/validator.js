// Input validation utilities
// Known bugs: see GitHub issues

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
  return phone.replace(/\D/g, '').length === 10;
}

function isValidAge(age) {
  return Number.isInteger(age) && age >= 0 && age <= 120;
}

function isNonEmptyString(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

module.exports = { isValidEmail, isValidPhone, isValidAge, isNonEmptyString };
