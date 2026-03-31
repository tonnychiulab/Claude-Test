// Calculator utility
// Known bugs: see GitHub issues

function add(a, b) {
  return a + b;
}

function subtract(a, b) {
  return a - b;
}

function multiply(a, b) {
  return a * b;
}

function divide(a, b) {
  // BUG #1: no division by zero check — returns Infinity
  return a / b;
}

function power(base, exp) {
  // BUG #2: does not handle negative exponent — returns NaN for power(2, -1)
  if (exp < 0) return NaN;
  return Math.pow(base, exp);
}

function percentage(value, total) {
  // BUG #3: no guard when total is 0 — returns Infinity
  return (value / total) * 100;
}

module.exports = { add, subtract, multiply, divide, power, percentage };
