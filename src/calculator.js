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
  if (b === 0) throw new Error("division by zero");
  return a / b;
}

function power(base, exp) {
  return Math.pow(base, exp);
}

function percentage(value, total) {
  if (total === 0) throw new Error("total cannot be zero");
  return (value / total) * 100;
}

module.exports = { add, subtract, multiply, divide, power, percentage };
