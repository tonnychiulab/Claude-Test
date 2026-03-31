// Input validation utilities
// Known bugs: see GitHub issues

function isValidEmail(email) {
  // BUG #4: regex too simple — accepts "user@" or "@domain.com"
  return email.includes('@');
}

function isValidPhone(phone) {
  // BUG #5: does not strip spaces/dashes before checking — rejects "091-234-5678"
  return /^\d{10}$/.test(phone);
}

function isValidAge(age) {
  // BUG #6: accepts negative ages and float ages — isValidAge(-5) returns true
  return typeof age === 'number' && age <= 120;
}

function isNonEmptyString(value) {
  // BUG #7: does not trim — isNonEmptyString("   ") returns true
  return typeof value === 'string' && value.length > 0;
}

module.exports = { isValidEmail, isValidPhone, isValidAge, isNonEmptyString };
