/** @type {import('jest').Config} */
module.exports = {
    testEnvironment: 'jest-environment-jsdom',
    testMatch: ['**/tests/js/**/*.test.js'],
    collectCoverageFrom: ['public/js/**/*.js'],
    coverageThreshold: {
        global: {
            lines: 80
        }
    }
};
