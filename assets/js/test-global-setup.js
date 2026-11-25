/**
 * Run JS tests in a fixed west-of-UTC timezone so UTC date-only parsing
 * regressions are observable on every machine, including CI containers.
 */
module.exports = async () => {
    process.env.TZ = 'America/Los_Angeles';
};
